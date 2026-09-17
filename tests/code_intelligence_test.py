import unittest

from nexus_code_model import CodeIntelligenceResult


class CodeIntelligenceTest(unittest.TestCase):
    def test_accepts_structured_read_only_evidence_for_multiple_languages(self):
        result = CodeIntelligenceResult.from_tool_result({
            "ok": True,
            "data": {
                "project": {"path": ".", "root_type": "directory", "read_only": True},
                "files": [
                    {"path": "app/Http/Controller.php", "type": "file", "language": "PHP"},
                    {"path": "resources/app.ts", "type": "file", "language": "TypeScript"},
                    {"path": "scripts/app.js", "type": "file", "language": "JavaScript"},
                ],
                "directories": [{"path": "app", "type": "directory"}],
                "symbols": [
                    {"name": "Controller", "kind": "class", "file": "app/Http/Controller.php"},
                    {"name": "run", "kind": "method", "file": "resources/app.ts"},
                    {"name": "load", "kind": "function", "file": "scripts/app.js"},
                    {"name": "Contract", "kind": "interface", "file": "resources/app.ts"},
                ],
                "imports": [{"file": "resources/app.ts", "target": "./api"}],
                "relations": [{"type": "route_to_controller", "source": "routes/web.php", "target": "Controller"}],
            },
        })
        self.assertTrue(result.read_only)
        self.assertEqual({item["language"] for item in result.files}, {"PHP", "TypeScript", "JavaScript"})
        self.assertEqual({item["kind"] for item in result.symbols}, {"class", "method", "function", "interface"})
        self.assertEqual(result.directories[0]["type"], "directory")

    def test_rejects_file_as_project_root(self):
        with self.assertRaises(ValueError):
            CodeIntelligenceResult.from_tool_result({
                "ok": True,
                "data": {"project": {"path": "app.php", "root_type": "file"}},
            })

    def test_rejects_write_capable_evidence(self):
        with self.assertRaises(ValueError):
            CodeIntelligenceResult.from_tool_result({
                "ok": True,
                "data": {"project": {"path": ".", "root_type": "directory", "read_only": False}},
            })


if __name__ == "__main__":
    unittest.main()
