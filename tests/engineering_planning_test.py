import unittest

from nexus_diagnostics import DiagnosticInput, NexusDiagnosticAI
from nexus_reasoning import EngineeringPlanningEngine, SolutionProposalEngine


class EngineeringPlanningTest(unittest.TestCase):
    def setUp(self):
        diagnosis = NexusDiagnosticAI().diagnose(DiagnosticInput(
            "db-plan",
            error="QueryException: relation users does not exist",
            logs=("SQLSTATE migration failed",),
            code=("app/Repositories/UserRepository.php:42",),
            recent_changes=("added users migration",),
            configuration=("database configured",),
        ))
        proposal_result = SolutionProposalEngine().propose(diagnosis)
        self.proposal = proposal_result.proposals[0]
        self.engine = EngineeringPlanningEngine()

    def test_generates_executable_but_not_executed_plan(self):
        plan = self.engine.create(self.proposal)
        self.assertEqual(plan.status, "ready_for_authorization")
        self.assertTrue(plan.executable)
        self.assertFalse(plan.executed)
        self.assertTrue(plan.objective)
        self.assertTrue(plan.preconditions)
        self.assertIsInstance(plan.affected_files, tuple)
        self.assertTrue(plan.required_tools)
        self.assertTrue(plan.required_permissions)
        self.assertTrue(plan.rollback)

    def test_every_step_has_explicit_dependencies_and_checkpoints(self):
        plan = self.engine.create(self.proposal)
        validation = self.engine.validate(plan)
        self.assertTrue(validation.valid, validation.errors)
        self.assertEqual(plan.steps[0].depends_on, ())
        self.assertEqual(plan.steps[1].depends_on, ("prepare",))
        self.assertEqual(plan.steps[2].depends_on, ("apply",))
        self.assertEqual(plan.steps[3].depends_on, ("test",))
        self.assertEqual(len(plan.checkpoints), len(plan.steps))

    def test_validation_rejects_unresolved_dependencies(self):
        plan = self.engine.create(self.proposal)
        broken = plan.__class__(
            **{
                **plan.__dict__,
                "steps": (
                    plan.steps[0].__class__(
                        "broken", "invalid", ("missing-step",), (), (), "checkpoint"
                    ),
                ),
            }
        )
        validation = self.engine.validate(broken)
        self.assertFalse(validation.valid)
        self.assertTrue(any("unresolved dependencies" in error for error in validation.errors))

    def test_plan_serialization_does_not_claim_execution(self):
        payload = self.engine.create(self.proposal).to_dict()
        self.assertTrue(payload["executable"])
        self.assertFalse(payload["executed"])
        self.assertEqual(payload["status"], "ready_for_authorization")


if __name__ == "__main__":
    unittest.main()
