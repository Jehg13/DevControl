import unittest

from nexus_ai.tools import NexusLaravelToolBridge, ToolBridgeError


READ_TOOLS = [
    {"name": "project.list", "permissions": ["nexus.read"], "requires_confirmation": False},
    {"name": "bug.list", "permissions": ["nexus.read"], "requires_confirmation": False},
]
WRITE_TOOLS = [
    {"name": "bug.update", "permissions": ["nexus.write"], "requires_confirmation": True},
]


class NexusToolBridgeTests(unittest.TestCase):
    def setUp(self):
        self.bridge = NexusLaravelToolBridge(READ_TOOLS + WRITE_TOOLS)

    def test_valid_read_proposal(self):
        proposal = self.bridge.propose("bug.list", {"project_id": 1})
        self.assertEqual(proposal.operation, "read")
        self.assertFalse(proposal.requires_confirmation)
        self.assertFalse(proposal.executable)

    def test_unknown_tool_is_rejected(self):
        with self.assertRaisesRegex(ToolBridgeError, "tool_not_found"):
            self.bridge.propose("bug.delete", {})

    def test_permission_mismatch_is_rejected(self):
        with self.assertRaisesRegex(ToolBridgeError, "permission_mismatch"):
            self.bridge.propose("bug.list", {}, requested_permissions=["nexus.write"])

    def test_write_proposal_requires_confirmation(self):
        proposal = self.bridge.propose("bug.update", {"id": 1, "status": "Cerrado"})
        self.assertEqual(proposal.operation, "write")
        self.assertTrue(proposal.requires_confirmation)
        self.assertFalse(proposal.executable)

    def test_bypass_arguments_are_rejected(self):
        with self.assertRaisesRegex(ToolBridgeError, "protected_argument"):
            self.bridge.propose("bug.update", {"id": 1, "permission": "admin"})

    def test_laravel_error_is_preserved(self):
        proposal = self.bridge.propose("bug.update", {"id": 1})
        result = self.bridge.accept_result(
            proposal,
            {"ok": False, "error": {"code": "confirmation_required", "message": "Confirm first"}},
        )
        self.assertFalse(result.successful)
        self.assertEqual(result.error["code"], "confirmation_required")

    def test_invalid_result_cannot_be_treated_as_success(self):
        proposal = self.bridge.propose("project.list")
        with self.assertRaisesRegex(ToolBridgeError, "boolean ok"):
            self.bridge.accept_result(proposal, {"data": []})


if __name__ == "__main__":
    unittest.main()
