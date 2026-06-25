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
- Flux cXML avec requête `PunchOutSetupRequest` et parsing du retour `PunchOutOrderMessage`.
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
- Mode d'ouverture : popup, nouvel onglet ou iframe
- Devise attendue
- TVA par défaut
- Création des produits absents
- Autorisation des prix à zéro
- Préfixe de référence produit
- Règle `PRICEUNIT`
- Durée de validité du token
- Durée de conservation des payloads
- Correspondances unités WURTH vers unités Dolibarr

Les réglages sont enregistrés par entité. Les secrets sont stockés via `dolEncrypt()` lorsque cette fonction est disponible.

## Flux utilisateur

1. L'utilisateur crée une commande fournisseur brouillon pour le tiers WURTH configuré.
2. Le bouton `Punchout WURTH` apparaît sur la fiche.
3. Le module crée une session Punchout temporaire et envoie l'utilisateur vers WURTH.
4. WURTH retourne le panier sur l'URL publique du module.
5. Le module stocke le payload brut et les lignes normalisées.
6. L'utilisateur confirme l'import depuis Dolibarr.
7. Le module crée ou retrouve les produits, met à jour les prix fournisseur et ajoute les lignes dans la commande.

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
- Retour cXML avec `PunchOutOrderMessage`.
- Import sans token CSRF refusé.
- Import avec token CSRF accepté.
- Double retour ou double import refusé.
- Prix à zéro refusé par défaut, puis accepté si l'option est activée.
- Devise différente de la devise attendue refusée.
- Produit existant par référence fournisseur.
- Produit créé avec préfixe configuré.
- Unité WURTH non mappée importée avec avertissement.
- Deux entités Multicompany avec configurations distinctes.
