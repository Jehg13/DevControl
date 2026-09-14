#!/usr/bin/env python3
from __future__ import annotations

import argparse
import hashlib
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_tokenizer import NexusTokenizer


def corpus_files(paths: list[str]) -> list[Path]:
    files: list[Path] = []
    for raw_path in paths:
        path = Path(raw_path)
        if path.is_file():
            files.append(path)
        elif path.is_dir():
            files.extend(sorted(item for item in path.rglob("*") if item.is_file()))
        else:
            raise FileNotFoundError(path)
    return sorted(set(files))


def read_corpus(paths: list[str]) -> tuple[list[str], list[str]]:
    texts: list[str] = []
    hashes: list[str] = []
    for path in corpus_files(paths):
        data = path.read_bytes()
        texts.append(data.decode("utf-8", errors="replace"))
        hashes.append(hashlib.sha256(data).hexdigest())
    return texts, hashes


def main() -> None:
    parser = argparse.ArgumentParser(description="Train and use the Nexus AI tokenizer.")
    subparsers = parser.add_subparsers(dest="command", required=True)

    train = subparsers.add_parser("train")
    train.add_argument("corpus", nargs="+")
    train.add_argument("--output", required=True)
    train.add_argument("--vocab-size", type=int, default=65536)
    train.add_argument("--min-frequency", type=int, default=2)

    encode = subparsers.add_parser("encode")
    encode.add_argument("tokenizer")
    encode.add_argument("text")
    encode.add_argument("--max-length", type=int)
    encode.add_argument("--truncation", action="store_true")
    encode.add_argument("--padding", action="store_true")

    decode = subparsers.add_parser("decode")
    decode.add_argument("tokenizer")
    decode.add_argument("ids", help="JSON array of integer token IDs")

    measure = subparsers.add_parser("measure")
    measure.add_argument("tokenizer")
    measure.add_argument("corpus", nargs="+")

    args = parser.parse_args()
    if args.command == "train":
        texts, hashes = read_corpus(args.corpus)
        tokenizer = NexusTokenizer.train(
            texts, vocab_size=args.vocab_size, min_frequency=args.min_frequency
        )
        tokenizer.save(args.output, hashes)
        print(json.dumps({"output": args.output, "vocab_size": tokenizer.vocab_size}, indent=2))
    elif args.command == "encode":
        tokenizer = NexusTokenizer.load(args.tokenizer)
        print(json.dumps(tokenizer.encode(
            args.text,
            max_length=args.max_length,
            truncation=args.truncation,
            padding=args.padding,
        )))
    elif args.command == "decode":
        tokenizer = NexusTokenizer.load(args.tokenizer)
        print(tokenizer.decode(json.loads(args.ids)))
    else:
        tokenizer = NexusTokenizer.load(args.tokenizer)
        texts, _ = read_corpus(args.corpus)
        print(json.dumps(tokenizer.measure(texts), indent=2))


if __name__ == "__main__":
    main()
