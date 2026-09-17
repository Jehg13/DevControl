import json
import subprocess
import sys
import unittest
from pathlib import Path


class NexusProcessEntrypointTests(unittest.TestCase):
    def test_json_process_delegates_to_application_handle(self):
        root = Path(__file__).resolve().parents[1]
        request = {
            "message": "Consulta el proyecto.",
            "context": {"values": {"project_id": 1}},
            "conversation": [],
            "permissions": ["nexus.read"],
            "tools": [],
            "metadata": {"session_id": "process-test"},
        }

        result = subprocess.run(
            [sys.executable, "-m", "nexus_ai"],
            input=json.dumps(request, ensure_ascii=False) + "\n",
            text=True,
            capture_output=True,
            cwd=root,
            check=False,
        )

        self.assertEqual(result.returncode, 0, result.stderr)
        response = json.loads(result.stdout)
        self.assertIn("interpretation", response)
        self.assertIn("status", response)
        self.assertNotEqual(response["status"], "not_implemented")

    def test_json_process_returns_structured_error_for_invalid_request(self):
        root = Path(__file__).resolve().parents[1]
        result = subprocess.run(
            [sys.executable, "-m", "nexus_ai"],
            input='{"message": ""}\n',
            text=True,
            capture_output=True,
            cwd=root,
            check=False,
        )

        self.assertEqual(result.returncode, 1)
        error = json.loads(result.stdout)["error"]
        self.assertEqual(error["code"], "python_request_error")


if __name__ == "__main__":
    unittest.main()
