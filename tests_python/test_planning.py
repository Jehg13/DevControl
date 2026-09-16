import unittest

from nexus_ai.planning import NexusPlanner, PlanRequest


class NexusPlanningTests(unittest.TestCase):
    def test_simple_plan_contains_structured_step(self):
        result = NexusPlanner().create_plan(PlanRequest("Revisa este proyecto", {"project": {"id": 1}}, ["nexus.read"]))
        self.assertEqual(result.status, "planned")
        self.assertEqual(result.steps[0].objective, "Analizar el objetivo solicitado")
        self.assertFalse(result.executable)

    def test_deployment_plan_has_dependencies_and_expected_sequence(self):
        result = NexusPlanner().create_plan(PlanRequest(
            "Quiero preparar este proyecto para desplegarlo.",
            {
                "project": {"id": 1},
                "configuration": {},
                "dependencies": [],
                "test_results": {},
                "repository": {},
                "review_results": {},
                "analysis": {},
            },
            ["nexus.read"],
        ))
        self.assertEqual(len(result.steps), 7)
        self.assertEqual(result.steps[1].depends_on, [1])
        self.assertEqual(result.steps[-1].depends_on, [1, 2, 3, 4, 5, 6])

    def test_missing_objective_is_reported(self):
        result = NexusPlanner().create_plan(PlanRequest("Ayúdame"))
        self.assertEqual(result.status, "insufficient_data")
        self.assertTrue(result.missing_information)

    def test_missing_permission_blocks_step_without_authorizing(self):
        result = NexusPlanner().create_plan(PlanRequest("prepara el despliegue", {"project": {"id": 1}}))
        self.assertEqual(result.status, "blocked")
        self.assertTrue(any(not step.feasible for step in result.steps))
        self.assertFalse(any(step.executable for step in result.steps))

    def test_missing_data_is_explicitly_blocked(self):
        result = NexusPlanner().create_plan(PlanRequest("prepara el despliegue", {"project": {"id": 1}}))
        self.assertEqual(result.status, "blocked")
        self.assertIn("Falta información", result.steps[1].blocker)

    def test_unavailable_tool_is_explicitly_blocked(self):
        result = NexusPlanner().create_plan(PlanRequest(
            "prepara el despliegue", {"project": {"id": 1}}, ["nexus.read"], [{"name": "project.inspect"}]
        ))
        self.assertEqual(result.status, "blocked")
        self.assertIn("no está disponible", result.steps[1].blocker)

    def test_confirmation_is_preserved_for_protected_steps(self):
        planner = NexusPlanner()
        planner._DEPLOY_TEMPLATE = planner._DEPLOY_TEMPLATE + (
            ("Publicar el despliegue", ["approval"], "deployment.publish", "publicación realizada", "alto", "deploy.write", True),
        )
        result = planner.create_plan(PlanRequest("prepara el despliegue", {"project": {"id": 1}}, ["nexus.read"]))
        publish = result.steps[-1]
        self.assertTrue(publish.requires_confirmation)
        self.assertEqual(publish.required_permission, "deploy.write")
        self.assertFalse(publish.executable)


if __name__ == "__main__":
    unittest.main()
