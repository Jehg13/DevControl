"""Small deterministic tokenizer used as a seam for future ML tokenizers."""

import re


class Tokenizer:
    def tokenize(self, text: str) -> list[str]:
        return re.findall(r"[a-záéíóúüñ0-9]+(?:[-_][a-záéíóúüñ0-9]+)*", text.casefold())
