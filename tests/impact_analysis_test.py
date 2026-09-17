import unittest

from nexus_code_model import CodeIntelligenceResult, ImpactAnalyzer


def intelligence(files, symbols, imports=(), relations=()):
    return CodeIntelligenceResult(
        project={"path": ".", "root_type": "directory", "read_only": True},
        files=tuple({"path": item, "type": "file", "language": "PHP"} for item in files),
        symbols=tuple(symbols),
        imports=tuple(imports),
        relations=tuple(relations),
    )


class ImpactAnalysisTest(unittest.TestCase):
    def test_isolated_change_has_no_hypothetical_impact(self):
        result = intelligence(["src/Standalone.php", "src/Unrelated.php"], [])
        graph = ImpactAnalyzer().analyze(["src/Standalone.php"], result)
        self.assertEqual(graph.changed, ("src/Standalone.php",))
        self.assertEqual(graph.components, ())
        self.assertEqual(graph.relations, ())

    def test_multiple_dependencies_preserve_direction_and_levels(self):
        result = intelligence(
            ["src/Repository.php", "src/Service.php", "src/Controller.php", "tests/ServiceTest.php"],
            [
                {"name": "Repository", "kind": "class", "file": "src/Repository.php"},
                {"name": "Service", "kind": "class", "file": "src/Service.php"},
                {"name": "Controller", "kind": "class", "file": "src/Controller.php"},
            ],
            imports=(
                {"file": "src/Service.php", "target": "./Repository"},
                {"file": "src/Controller.php", "target": "./Service"},
            ),
            relations=(
                {"type": "test_covers", "source": "tests/ServiceTest.php", "target": "Service"},
            ),
        )
        graph = ImpactAnalyzer().analyze(["src/Repository.php"], result)
        impacted = {item.component: item for item in graph.components}
        self.assertIn("src/Service.php", impacted)
        self.assertIn("src/Controller.php", impacted)
        self.assertEqual(impacted["src/Service.php"].impact_level, "direct")
        self.assertEqual(impacted["src/Controller.php"].impact_level, "transitive")
        self.assertTrue(any(edge.relation == "imports" for edge in graph.relations))
        self.assertTrue(all(edge.direction in {"forward", "reverse"} for edge in graph.relations))
        self.assertNotIn("src/Unrelated.php", impacted)

    def test_symbol_change_uses_real_file_symbol_relationship(self):
        result = intelligence(
            ["src/Service.php", "src/Controller.php"],
            [
                {"name": "Service", "kind": "class", "file": "src/Service.php"},
                {"name": "Controller", "kind": "class", "file": "src/Controller.php"},
            ],
            relations=(
                {"type": "calls", "source": "Controller", "target": "Service"},
            ),
        )
        graph = ImpactAnalyzer().analyze(
            [{"path": "src/Service.php", "type": "file"}],
            result,
        )
        self.assertTrue(any(item.component == "src/Controller.php::Controller" for item in graph.components))

    def test_unresolved_import_is_not_presented_as_impact(self):
        result = intelligence(
            ["src/A.php"],
            [],
            imports=({"file": "src/A.php", "target": "./Missing"},),
        )
        graph = ImpactAnalyzer().analyze(["src/A.php"], result)
        self.assertEqual(graph.components, ())
        self.assertEqual(graph.relations, ())


if __name__ == "__main__":
    unittest.main()
