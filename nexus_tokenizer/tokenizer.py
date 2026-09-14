from __future__ import annotations

import hashlib
import json
from collections import Counter
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable, Sequence


TOKENIZER_VERSION = "1.0.0"
BYTE_TOKEN_COUNT = 256


@dataclass(frozen=True)
class SpecialTokens:
    pad: str = "<|pad|>"
    bos: str = "<|bos|>"
    eos: str = "<|eos|>"
    unk: str = "<|unk|>"
    fim_prefix: str = "<|fim_prefix|>"
    fim_suffix: str = "<|fim_suffix|>"
    fim_middle: str = "<|fim_middle|>"
    tool_call: str = "<|tool_call|>"
    tool_result: str = "<|tool_result|>"
    system: str = "<|system|>"
    user: str = "<|user|>"
    assistant: str = "<|assistant|>"

    def ordered(self) -> list[str]:
        return [
            self.pad,
            self.bos,
            self.eos,
            self.unk,
            self.fim_prefix,
            self.fim_suffix,
            self.fim_middle,
            self.tool_call,
            self.tool_result,
            self.system,
            self.user,
            self.assistant,
        ]


def _hex_token(value: bytes) -> str:
    return f"<|b:{value.hex()}|>"


def _canonical_json(value: object) -> bytes:
    return json.dumps(value, ensure_ascii=True, sort_keys=True, separators=(",", ":")).encode()


class NexusTokenizer:
    """A deterministic byte-level BPE tokenizer with exact round-tripping.

    Tokens are represented as hexadecimal byte strings in the serialized
    vocabulary. This keeps the artifact portable while retaining arbitrary
    UTF-8, source code, logs, and binary-looking snippets without an unknown
    character fallback.
    """

    def __init__(
        self,
        merges: Sequence[tuple[bytes, bytes]] = (),
        special_tokens: SpecialTokens | None = None,
        version: str = TOKENIZER_VERSION,
    ) -> None:
        self.version = version
        self.special_tokens = special_tokens or SpecialTokens()
        self.merges = list(merges)
        self._merge_ranks = {pair: rank for rank, pair in enumerate(self.merges)}
        self._special_to_id = {
            token: BYTE_TOKEN_COUNT + index
            for index, token in enumerate(self.special_tokens.ordered())
        }
        self._id_to_special = {value: key for key, value in self._special_to_id.items()}
        self._byte_to_id = {bytes([value]): value for value in range(BYTE_TOKEN_COUNT)}
        self._token_to_id = {
            value: BYTE_TOKEN_COUNT + len(self._special_to_id) + index
            for index, value in enumerate(self._merge_tokens())
        }
        self._id_to_token = {value: token for token, value in self._token_to_id.items()}

    def _merge_tokens(self) -> list[bytes]:
        tokens: list[bytes] = []
        for left, right in self.merges:
            merged = left + right
            if merged not in tokens:
                tokens.append(merged)
        return tokens

    @property
    def vocab_size(self) -> int:
        return BYTE_TOKEN_COUNT + len(self._special_to_id) + len(self._token_to_id)

    @classmethod
    def train(
        cls,
        texts: Iterable[str],
        vocab_size: int = 65536,
        min_frequency: int = 2,
        special_tokens: SpecialTokens | None = None,
    ) -> "NexusTokenizer":
        if vocab_size <= BYTE_TOKEN_COUNT:
            raise ValueError("vocab_size must be greater than 256")
        if min_frequency < 1:
            raise ValueError("min_frequency must be at least 1")

        sequences = [list(text.encode("utf-8")) for text in texts]
        symbol_bytes = {value: bytes([value]) for value in range(BYTE_TOKEN_COUNT)}
        merges: list[tuple[bytes, bytes]] = []
        special_count = len((special_tokens or SpecialTokens()).ordered())
        merge_limit = vocab_size - BYTE_TOKEN_COUNT - special_count

        while len(merges) < merge_limit:
            pair_counts: Counter[tuple[int, int]] = Counter()
            for sequence in sequences:
                pair_counts.update(zip(sequence, sequence[1:]))
            candidates = [
                (count, pair)
                for pair, count in pair_counts.items()
                if count >= min_frequency
            ]
            if not candidates:
                break
            _, (left, right) = max(
                candidates,
                key=lambda item: (
                    item[0],
                    symbol_bytes[item[1][0]] + symbol_bytes[item[1][1]],
                ),
            )
            left_bytes = symbol_bytes[left]
            right_bytes = symbol_bytes[right]
            merges.append((left_bytes, right_bytes))
            merged_symbol = BYTE_TOKEN_COUNT + len(merges) - 1
            symbol_bytes[merged_symbol] = left_bytes + right_bytes
            for sequence in sequences:
                index = 0
                while index < len(sequence) - 1:
                    if sequence[index] == left and sequence[index + 1] == right:
                        sequence[index:index + 2] = [merged_symbol]
                        index += 1
                    else:
                        index += 1

        return cls(merges=merges, special_tokens=special_tokens)

    def _encode_bytes(self, value: bytes) -> list[bytes]:
        symbols = [bytes([byte]) for byte in value]
        for left, right in self.merges:
            index = 0
            merged = left + right
            while index < len(symbols) - 1:
                if symbols[index] == left and symbols[index + 1] == right:
                    symbols[index:index + 2] = [merged]
                else:
                    index += 1
        return symbols

    def _split_specials(self, text: str) -> list[str | bytes]:
        specials = sorted(self._special_to_id, key=len, reverse=True)
        parts: list[str | bytes] = []
        cursor = 0
        while cursor < len(text):
            match = next((token for token in specials if text.startswith(token, cursor)), None)
            if match is not None:
                parts.append(match)
                cursor += len(match)
                continue
            next_positions = [text.find(token, cursor + 1) for token in specials]
            next_positions = [position for position in next_positions if position >= 0]
            end = min(next_positions, default=len(text))
            parts.append(text[cursor:end].encode("utf-8"))
            cursor = end
        return parts

    def encode(
        self,
        text: str,
        *,
        add_bos: bool = False,
        add_eos: bool = False,
        max_length: int | None = None,
        truncation: bool = False,
        padding: bool = False,
    ) -> list[int]:
        ids: list[int] = []
        if add_bos:
            ids.append(self._special_to_id[self.special_tokens.bos])
        for part in self._split_specials(text):
            if isinstance(part, str):
                ids.append(self._special_to_id[part])
            else:
                ids.extend(self._id_for_bytes(token) for token in self._encode_bytes(part))
        if add_eos:
            ids.append(self._special_to_id[self.special_tokens.eos])
        if max_length is not None:
            if max_length < 1:
                raise ValueError("max_length must be positive")
            if len(ids) > max_length:
                if not truncation:
                    raise ValueError("encoded sequence exceeds max_length and truncation is disabled")
                ids = ids[:max_length]
            elif padding:
                ids.extend([self._special_to_id[self.special_tokens.pad]] * (max_length - len(ids)))
        return ids

    def _id_for_bytes(self, token: bytes) -> int:
        return self._byte_to_id.get(token, self._token_to_id.get(token, self._special_to_id[self.special_tokens.unk]))

    def decode(self, ids: Sequence[int], *, skip_special_tokens: bool = False) -> str:
        output = bytearray()
        for token_id in ids:
            if token_id in self._id_to_special:
                if not skip_special_tokens:
                    output.extend(self._id_to_special[token_id].encode("utf-8"))
                continue
            token = self._id_to_token.get(token_id)
            if token is None:
                if 0 <= token_id < BYTE_TOKEN_COUNT:
                    token = bytes([token_id])
                else:
                    raise ValueError(f"unknown token id: {token_id}")
            output.extend(token)
        return output.decode("utf-8", errors="replace")

    def measure(self, texts: Iterable[str]) -> dict[str, float | int]:
        values = list(texts)
        encoded = [self.encode(value) for value in values]
        characters = sum(len(value) for value in values)
        raw_bytes = sum(len(value.encode("utf-8")) for value in values)
        token_count = sum(len(value) for value in encoded)
        return {
            "documents": len(values),
            "characters": characters,
            "bytes": raw_bytes,
            "tokens": token_count,
            "tokens_per_document": token_count / len(values) if values else 0,
            "tokens_per_byte": token_count / raw_bytes if raw_bytes else 0,
            "compression_ratio": raw_bytes / token_count if token_count else 0,
        }

    def to_dict(self, corpus_hashes: Sequence[str] = ()) -> dict:
        artifact = {
            "format": "nexus-byte-bpe",
            "version": self.version,
            "vocab_size": self.vocab_size,
            "special_tokens": self.special_tokens.ordered(),
            "merges": [[_hex_token(left), _hex_token(right)] for left, right in self.merges],
            "training": {"corpus_sha256": list(corpus_hashes)},
        }
        artifact["sha256"] = hashlib.sha256(_canonical_json(artifact)).hexdigest()
        return artifact

    def save(self, path: str | Path, corpus_hashes: Sequence[str] = ()) -> None:
        Path(path).write_text(
            json.dumps(self.to_dict(corpus_hashes), ensure_ascii=True, indent=2) + "\n",
            encoding="utf-8",
        )

    @classmethod
    def load(cls, path: str | Path) -> "NexusTokenizer":
        artifact = json.loads(Path(path).read_text(encoding="utf-8"))
        if artifact.get("format") != "nexus-byte-bpe":
            raise ValueError("unsupported tokenizer format")
        expected = artifact.pop("sha256", None)
        actual = hashlib.sha256(_canonical_json(artifact)).hexdigest()
        if expected != actual:
            raise ValueError("tokenizer artifact integrity check failed")
        specials = SpecialTokens(*artifact["special_tokens"])
        merges = [
            (bytes.fromhex(left[4:-2]), bytes.fromhex(right[4:-2]))
            for left, right in artifact["merges"]
        ]
        tokenizer = cls(merges=merges, special_tokens=specials, version=artifact["version"])
        if tokenizer.vocab_size != artifact["vocab_size"]:
            raise ValueError("tokenizer vocabulary size does not match artifact")
        return tokenizer
