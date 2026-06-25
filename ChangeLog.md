# ChangeLog

## 1.0.0 - 2026-06-25

- Création du module externe `wurthpunchout`.
- Ajout du descripteur `modWurthPunchout` avec droits, hook commande fournisseur et cron natif.
- Ajout des pages de configuration, compatibilité, sessions et à propos.
- Ajout du flux OCI avec retour panier `NEW_ITEM-*`.
- Ajout du flux cXML avec `PunchOutSetupRequest` et parsing `PunchOutOrderMessage`.
- Ajout des sessions Punchout sécurisées par token à usage unique.
- Ajout de l'import authentifié des paniers vers commandes fournisseurs brouillon.
- Ajout de la création produit optionnelle et de la mise à jour des prix fournisseur WURTH.
- Ajout des tables SQL `wurthpunchout_session`, `wurthpunchout_session_line` et `wurthpunchout_unitmap`.
- Ajout des traductions `fr_FR` et `en_US`.
- Ajout de la règle Multicompany V1 : refus d'import depuis une entité différente de l'entité propriétaire de la commande.
