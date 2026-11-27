#!/usr/bin/env python3
"""
Validate the data-as-configuration files against each other.

  python3 schema/validate.py

Checks:
  * every feature referenced by a package exists in the registry
  * every feature in the registry is priced in every package version
  * feature values match their declared type
  * packages are monotonic - a higher tier never grants less than a lower one
  * every wizard field's requiresFeature / gate refers to a real feature
  * every wizard visibleWhen refers to a field in the same step
  * every wizard step key is unique, as is every field key within a step

Exits non-zero on any error, so it can run in CI.
"""
import json, sys, pathlib

HERE = pathlib.Path(__file__).parent
errors: list[str] = []


def err(msg: str) -> None:
    errors.append(msg)


def load(name: str):
    return json.loads((HERE / name).read_text())


def check_packages(features, packages):
    keys = set(features)
    for p in packages:
        for v in p["versions"]:
            have = set(v["features"])
            for k in sorted(keys - have):
                err(f"{p['key']} v{v['version']}: no value for feature {k}")
            for k in sorted(have - keys):
                err(f"{p['key']} v{v['version']}: unknown feature {k}")
            for k, val in v["features"].items():
                if k not in features:
                    continue
                t = features[k]["valueType"]
                ok = (
                    (t == "boolean" and isinstance(val, bool))
                    or (t == "limit" and isinstance(val, int) and not isinstance(val, bool))
                    or (t == "enum" and val in features[k].get("enumOptions", []))
                )
                if not ok:
                    err(f"{p['key']} v{v['version']}: {k}={val!r} is not a valid {t}")

    order = sorted(packages, key=lambda p: p["sortOrder"])
    for lo, hi in zip(order, order[1:]):
        lf, hf = lo["versions"][0]["features"], hi["versions"][0]["features"]
        for k, lv in lf.items():
            if k not in hf or k not in features:
                continue
            t = features[k]["valueType"]
            if t == "boolean" and lv and not hf[k]:
                err(f"{hi['key']} removes {k}, which {lo['key']} grants")
            if t == "limit" and hf[k] != -1 and lv > hf[k]:
                err(f"{hi['key']} lowers {k}: {lo['key']}={lv} > {hi['key']}={hf[k]}")


def check_wizard(features, wizard):
    seen_steps: set[str] = set()
    for step in wizard["steps"]:
        if step["key"] in seen_steps:
            err(f"duplicate wizard step key: {step['key']}")
        seen_steps.add(step["key"])

        gate = step.get("requiresFeature")
        if gate and gate not in features:
            err(f"step {step['key']}: requiresFeature {gate} is not in the registry")

        field_keys = {f["key"] for f in step.get("fields", [])}
        seen_fields: set[str] = set()
        for f in step.get("fields", []):
            if f["key"] in seen_fields:
                err(f"step {step['key']}: duplicate field key {f['key']}")
            seen_fields.add(f["key"])

            fgate = f.get("requiresFeature")
            if fgate and fgate not in features:
                err(f"{step['key']}.{f['key']}: requiresFeature {fgate} is not in the registry")

            for cond in conditions(f.get("visibleWhen")):
                ref = cond.get("field")
                if ref and ref not in field_keys:
                    err(f"{step['key']}.{f['key']}: visibleWhen refers to unknown field {ref!r}")

            if f.get("sensitive") and f.get("translatable"):
                err(f"{step['key']}.{f['key']}: a sensitive field must never be translatable")


def conditions(node):
    """Flatten allOf / anyOf trees into leaf conditions."""
    if not node:
        return []
    if "allOf" in node or "anyOf" in node:
        out = []
        for child in node.get("allOf", []) + node.get("anyOf", []):
            out.extend(conditions(child))
        return out
    return [node]


def main() -> int:
    features = {f["key"]: f for f in load("features.json")["features"]}
    packages = load("seed-packages.json")["packages"]
    wizard = load("wizard-schema.json")

    check_packages(features, packages)
    check_wizard(features, wizard)

    if errors:
        print(f"{len(errors)} problem(s):\n")
        for e in errors:
            print(f"  - {e}")
        return 1

    steps = len(wizard["steps"])
    fields = sum(len(s.get("fields", [])) for s in wizard["steps"])
    print(
        f"OK  {len(features)} features, {len(packages)} packages, "
        f"{steps} wizard steps, {fields} wizard fields - all cross-references resolve"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
