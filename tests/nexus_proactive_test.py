import unittest

from nexus_proactive import DetectionInput, ProactiveDetector


class NexusProactiveTest(unittest.TestCase):
    def test_correlates_signals_and_escalates_severity(self):
        alerts = ProactiveDetector().detect(DetectionInput(
            project="devcontrol",
            changes=("breaking change in production deployment",),
            logs=("error rate spike and timeout",),
            metrics=("latency anomalous",),
            location="api",
        ))
        names = {alert.problem for alert in alerts}
        self.assertTrue({"dangerous change", "degradation", "anomaly"} <= names)
        dangerous = next(alert for alert in alerts if alert.problem == "dangerous change")
        self.assertEqual(dangerous.severity, "CRITICAL")
        self.assertTrue(dangerous.to_devcontrol()["proactive"])

    def test_weak_normal_signals_do_not_create_alerts(self):
        alerts = ProactiveDetector().detect(DetectionInput(
            project="devcontrol",
            logs=("normal request completed",),
            metrics=("latency stable",),
        ))
        self.assertEqual(alerts, ())

    def test_duplicate_alerts_are_deterministic_and_thresholded(self):
        value = DetectionInput(project="p", errors=("recurrent failure",), bugs=("recurrent bug",))
        first = ProactiveDetector().detect(value)
        second = ProactiveDetector().detect(value)
        self.assertEqual(first, second)
        self.assertEqual(len(first), 1)
        self.assertGreaterEqual(first[0].confidence, 0.45)


if __name__ == "__main__":
    unittest.main()
