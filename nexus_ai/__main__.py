"""Line-oriented JSON entry point used by Laravel's local adapter."""

import json
import sys

from nexus_ai.api.application import NexusAiApplication
from nexus_ai.api.contracts import NexusAiRequest


def main() -> int:
    application = NexusAiApplication()
    for line in sys.stdin:
        if not line.strip():
            continue
        try:
            request = NexusAiRequest.from_dict(json.loads(line))
            response = application.handle(request)
            print(json.dumps(response.to_dict(), ensure_ascii=False), flush=True)
        except Exception as error:
            print(
                json.dumps(
                    {
                        "error": {
                            "code": "python_request_error",
                            "message": str(error),
                        }
                    },
                    ensure_ascii=False,
                ),
                flush=True,
            )
            return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
