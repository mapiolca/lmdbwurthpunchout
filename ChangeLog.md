# ChangeLog

## Unreleased

- Correction de la structure `PunchOutSetupRequest` cXML : `SharedSecret` est désormais porté par `Sender/Credential`.
- Ajout de la prise en charge des retours panier cXML en `cXML-base64`.
- Ajout d’identifiants `Sender` cXML optionnels avec repli sur les identifiants client.
- Journalisation assainie des rejets de setup cXML.

## 1.0.0 - 2026-06-25

- Création du module externe `lmdbwurthpunchout`.
- Alignement des numéros de permissions sur le modèle `$this->numero * 100 + $r`.
