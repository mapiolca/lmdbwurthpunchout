# AGENT.md - WURTH Punchout

This repository is the root of the Dolibarr external module `wurthpunchout`.

Implementation rules:

- Do not modify Dolibarr core files.
- Keep the module installable under `htdocs/custom/wurthpunchout`.
- Keep `config_page_url` limited to `setup.php@wurthpunchout`.
- Preserve Dolibarr v20+ and PHP 8.0+ compatibility.
- Store business settings per entity.
- Keep Punchout imports restricted to the active owner entity of the supplier order.
- Use native Dolibarr supplier order, product and supplier price classes.
- Public return endpoints may only store the returned basket; the real import must remain authenticated and CSRF-protected.
- Do not log WURTH passwords, shared secrets, complete OCI URLs containing secrets, or raw customer-sensitive payloads in syslog.
- Update `README.md`, `ChangeLog.md`, `langs/fr_FR/wurthpunchout.lang` and `langs/en_US/wurthpunchout.lang` for user-visible changes.
