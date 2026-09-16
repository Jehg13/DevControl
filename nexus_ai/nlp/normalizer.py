"""Spanish text normalization without external NLP dependencies."""

import re
import unicodedata


class TextNormalizer:
    _TYPO_CORRECTIONS = {
        "bugs abiertos": "bugs abiertos",
        "buggs": "bugs",
        "proyetos": "proyectos",
        "tareas pendietes": "tareas pendientes",
        "incidnetes": "incidentes",
        "actualizacoines": "actualizaciones",
        "ejecuta los test": "ejecuta los tests",
    }

    def normalize(self, text: str) -> str:
        normalized = unicodedata.normalize("NFKC", text).casefold()
        normalized = normalized.replace("¿", " ").replace("¡", " ")
        normalized = re.sub(r"\s+", " ", normalized).strip()
        for source, replacement in self._TYPO_CORRECTIONS.items():
            normalized = normalized.replace(source, replacement)
        return normalized

    def without_accents(self, text: str) -> str:
        return "".join(
            char for char in unicodedata.normalize("NFD", text)
            if unicodedata.category(char) != "Mn"
        )
