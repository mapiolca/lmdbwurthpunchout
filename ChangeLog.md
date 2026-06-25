# ChangeLog

## 1.0.0 - 2026-06-25

- Création du module externe `lmdbwurthpunchout`.
- Ajout du descripteur `modLmdbWurthPunchout` avec droits, hook commande fournisseur et cron natif.
- Ajout des pages de configuration, compatibilité, sessions et à propos.
- Ajout du flux OCI avec retour panier `NEW_ITEM-*`.
- Ajout du flux cXML avec `PunchOutSetupRequest` et parsing `PunchOutOrderMessage`.
- Ajout des sessions Punchout sécurisées par token à usage unique.
- Ajout de l'import authentifié des paniers vers commandes fournisseurs brouillon.
- Ajout de la création produit optionnelle et de la mise à jour des prix fournisseur WURTH.
- Ajout des tables SQL `lmdbwurthpunchout_session`, `lmdbwurthpunchout_session_line` et `lmdbwurthpunchout_unitmap`.
- Ajout des traductions `fr_FR` et `en_US`.
- Ajout de la règle Multicompany V1 : refus d'import depuis une entité différente de l'entité propriétaire de la commande.
- Ajout du picto carré WURTH pour le module.
- Correction du filtre fournisseur de la page de réglages avec la syntaxe USF requise par Dolibarr 24.
- Correction de la référence du picto pour l'affichage dans la liste des modules Dolibarr.
- Ajout de l'action de création ou complétion du tiers fournisseur WURTH France depuis les réglages.
- Correction de la classe de hooks pour éviter une redéclaration PHP entre deux variantes de casse.
- Correction du helper interne de session pour éviter une collision avec `CommonObject::fetchCommon()`.
- Ajout du constructeur de session pour initialiser le handler base de données.
- Correction du parsing OCI pour accepter les champs `NEW_ITEM-*` reçus par PHP sous forme de tableaux indexés.
- Import automatique du panier dès le retour WURTH, sans écran de confirmation intermédiaire.
- Correction des endpoints de retour WURTH pour éviter la création d’une session Dolibarr anonyme qui pouvait remplacer la session utilisateur active.
- Ajout du mode d’ouverture en modale directement depuis la fiche commande fournisseur, avec secours en nouvelle fenêtre.
- Complément des traductions manquantes et alignement de l’en-tête des réglages sur la présentation native Dolibarr.
