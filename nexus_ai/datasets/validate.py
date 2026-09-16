"""Command-line validator for the checked-in Nexus dataset files."""

from pathlib import Path

from .validator import validate_dataset_directory


if __name__ == "__main__":
    result = validate_dataset_directory(Path(__file__).parent)
    print(f"Validated {result['records']} records in {result['files']} files.")
