---
status: shipped
shipped: 2026-07-13
---

# Import

* The app should be able to import zip files created through the export feature.
* The imported project should not collide with existing ones. We do not replace existing projects. In case of identical names, we add the datetime at the end of the project name for differentiation.
* Security is critical. The user must not be able to upload backdoors/scripts/etc. The structure of the imported zip should be verified:
  * is it a zip?
  * is the arborescence valid?
  * are there files that are not supposed to be there?
  * does each file validate as exactly what it is supposed to be?
  * do the markdown files contain only markdown and html?
* The project is created for the current user.
