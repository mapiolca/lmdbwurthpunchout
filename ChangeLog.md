# ChangeLog

## À publier — compléments à la version 1.0.1

- Conserve toutes les sessions lors du changement de pagination avec les filtres natifs laissés sur « tous ».

- Réserve les retours OCI/cXML à la réception du panier ; l’import nécessite une confirmation dans une session authentifiée avec protection CSRF et redirection après succès.
- Respecte directement les permissions natives, sans élévation administrateur implicite, et migre le droit d’utilisation de l’offset 0 vers 3 en conservant les attributions utilisateurs/groupes et les offsets 1/2.
- Isole les règles REP par entité, traite les erreurs de lecture comme des erreurs et respecte les précisions monétaires natives.
- Utilise les lecteurs de constantes natifs, corrige le formulaire de pagination des sessions et renomme les instructions locales en `AGENTS.md`.

## 1.0.1 - 2026-10-11

- Restaure la vignette du module dans Dolibarr 25 alpha en fournissant aussi le nom d’image sans préfixe `object_`, tout en conservant l’image utilisée par Dolibarr v20 à v24.

## 1.0.0 - 2026-06-25

- Création du module externe `lmdbwurthpunchout`.
