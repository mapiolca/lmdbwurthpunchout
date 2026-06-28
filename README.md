# LmdbWurthPunchout

Module externe Dolibarr pour importer un panier Punchout WURTH dans une commande fournisseur brouillon.

## Compatibilité

- Dolibarr : v20+
- PHP : 8.0+
- Base : MySQL/MariaDB via l'abstraction Dolibarr
- Emplacement d'installation : `htdocs/custom/lmdbwurthpunchout`

Le dépôt contient directement la racine du module. Pour l'installer, placer ce répertoire dans `htdocs/custom/lmdbwurthpunchout`, puis activer le module depuis la liste des modules Dolibarr.

## Dépendances

- Module Fournisseurs / commandes fournisseurs
- Module Produits / services

## Fonctionnalités

- Bouton `Punchout WURTH` ajouté par hook sur la fiche commande fournisseur.
- Affichage du bouton uniquement si la commande est brouillon, liée au tiers WURTH configuré, dans l'entité active propriétaire, et si l'utilisateur a les droits nécessaires.
- Flux OCI avec `ORGANIZATION`, `NAME`, `PASSWORD` et `HOOK_URL`.
- Flux cXML avec requête `PunchOutSetupRequest` conforme, `SharedSecret` dans `Sender/Credential`, et parsing du retour `PunchOutOrderMessage`.
- Retour panier cXML en `cXML-urlencoded` ou `cXML-base64`.
- Conservation des métadonnées cXML du panier : frais de port, total, taxe, adresse de livraison et identifiants complémentaires de lignes.
- Import optionnel des frais de port cXML positifs comme ligne de commande fournisseur.
- Fallback optionnel WURTH : si `Shipping/Money` vaut zéro mais que la taxe d’en-tête contient des frais annexes, le module peut créer une ligne de port et une ligne `REP Taxe n/w` séparées.
- Barème REP cXML par référence fournisseur WURTH, avec montant HT unitaire multiplié par la quantité retournée.
- Session Punchout temporaire avec token aléatoire à usage unique.
- Retour panier public qui stocke le payload sans modifier la commande.
- Import authentifié avec token CSRF Dolibarr.
- Recherche produit par prix fournisseur WURTH, puis par référence produit générée, puis création optionnelle.
- Création ou mise à jour du prix fournisseur WURTH via `ProductFournisseur`.
- Ajout des lignes via `CommandeFournisseur::addline()`.
- Cron natif désactivé par défaut pour expirer les sessions et purger les payloads anciens.

## Configuration

Le seul point d'entrée déclaré est :

```text
admin/setup.php@lmdbwurthpunchout
```

Onglets internes disponibles :

- Réglages
- Compatibilité
- Sessions Punchout
- À propos

Paramètres principaux :

- Protocole : `OCI` ou `cXML`
- Tiers fournisseur WURTH
- Action de création ou de complétion du tiers fournisseur `WURTH FRANCE`
- Mode d'ouverture : modale sur la commande fournisseur, nouvelle fenêtre ou nouvel onglet
- Devise attendue
- TVA par défaut
- Création des produits absents
- Autorisation des prix à zéro
- Préfixe de référence produit
- Règle `PRICEUNIT`
- Durée de validité du token
- Durée de conservation des payloads
- Correspondances unités WURTH vers unités Dolibarr
- Import des frais de port cXML, déduction optionnelle depuis l’écart de TVA WURTH, produit/service de frais de port optionnel et TVA dédiée optionnelle
- Import REP cXML, barème REP par référence fournisseur WURTH, repli global désactivé par défaut, produit/service REP optionnel et TVA REP dédiée optionnelle

Les réglages sont enregistrés par entité. Les secrets sont stockés via `dolEncrypt()` lorsque cette fonction est disponible.

La page de réglages permet de créer ou compléter le tiers fournisseur `WURTH FRANCE` avec ses informations légales principales, puis de le sélectionner automatiquement comme tiers WURTH du module.

## Flux utilisateur

1. L'utilisateur crée une commande fournisseur brouillon pour le tiers WURTH configuré.
2. Le bouton `Punchout WURTH` apparaît sur la fiche.
3. Le module crée une session Punchout temporaire et envoie l'utilisateur vers WURTH.
4. WURTH retourne le panier sur l'URL publique du module.
5. Le module stocke le payload brut et les lignes normalisées, puis importe automatiquement le panier.
6. Le module crée ou retrouve les produits, met à jour les prix fournisseur et ajoute les lignes dans la commande.
7. L'utilisateur est renvoyé vers la commande fournisseur.

## Multicompany

La V1 refuse le lancement et l'import lorsque la commande fournisseur appartient à une autre entité que l'entité active. Cette règle évite d'écrire des produits, prix fournisseur ou lignes de commande dans une mauvaise entité lors de la consultation d'une commande partagée.

## Hors périmètre V1

- Envoi final de la commande à WURTH par EDI ORDER.
- Envoi final automatique du PDF de commande par email.
- Support complet d'import depuis une entité différente de l'entité propriétaire.

## Tests recommandés

- Activation, désactivation et réactivation du module.
- Page de compatibilité.
- Configuration OCI complète et incomplète.
- Configuration cXML complète et incomplète.
- Bouton visible/invisible selon tiers, statut, droits et entité.
- Retour OCI avec champs `NEW_ITEM-*`.
- Retour cXML avec `PunchOutOrderMessage` en `cXML-urlencoded`.
- Retour cXML avec `PunchOutOrderMessage` en `cXML-base64`.
- Retour cXML avec frais de port à zéro et sans écart de TVA : aucune ligne de frais de port ajoutée.
- Retour cXML avec frais de port à zéro et écart de TVA WURTH : lignes de frais de port et REP déduites si l’option est activée.
- Retour cXML avec frais de port positif : ligne de frais de port ajoutée, en ligne libre ou avec le produit/service configuré.
- Retour cXML avec REP paramétrée par référence WURTH : montant REP multiplié par la quantité de la ligne.
- Retour cXML avec REP désactivée, sans règle applicable et sans montant de repli : aucune ligne REP ajoutée.
- Import sans token CSRF refusé.
- Import avec token CSRF accepté.
- Double retour ou double import refusé.
- Prix à zéro refusé par défaut, puis accepté si l'option est activée.
- Devise différente de la devise attendue refusée.
- Produit existant par référence fournisseur.
- Produit créé avec préfixe configuré.
- Unité WURTH non mappée importée avec avertissement.
- Deux entités Multicompany avec configurations distinctes.
