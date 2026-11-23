"""Select and verify a local Docker image on older and newer Docker clients."""

from functools import lru_cache
import json
import re
import subprocess


@lru_cache
def supports_platform(command):
    help_text = subprocess.check_output(["docker", "image", command, "--help"], text=True)
    return "--platform" in help_text.split()


def platform_options(command, platform):
    return ["--platform", platform] if supports_platform(command) else []


def inspect_image(image, platform):
    command = ["docker", "image", "inspect", *platform_options("inspect", platform), image]
    records = json.loads(subprocess.check_output(command, text=True))
    if len(records) != 1:
        raise ValueError("Expected one local Docker image.")
    record = records[0]
    if record["Os"] + "/" + record["Architecture"] != platform or not re.fullmatch(r"sha256:[0-9a-f]{64}", record["Id"]):
        raise ValueError("Local Docker image has the wrong platform or configuration ID: " + image)
    return record
