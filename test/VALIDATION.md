# Validation de la préparation 1.0.1

Contrôles locaux du 2026-10-11, PHP 8.5.7. Ces simulations sont distinctes de la recette distante décrite ci-dessous.

- Syntaxe PHP de tous les fichiers du module et `git diff --check`.
- `php test/parser_test.php` et `php test/importer_rep_test.php` : scénarios existants de parsing OCI/cXML et de REP, environnement Dolibarr simulé.
- `php test/security_return_test.php` : réception du panier sans import, rejet du retour répété, administrateur sans permissions refusé, isolation REP, contrôle d’état sous verrou simulé, échec de commit de réception et tokens CSRF absents/invalides/valides. Utilisateurs et sessions simulés.
- `php test/permission_migration_test.php <dolibarr>/htdocs` : conservation des attributions et entités, migration répétée, rollback après échec injecté, collision refusée. SQLite en mémoire avec INSERT IGNORE traduit en INSERT OR IGNORE ; ce n’est pas un essai MySQL/MariaDB.
- `php test/native_precision_test.php <functions.lib.php>` : exécution sans modification des helpers natifs extraits de sources figées, scénarios REP et variation de la précision des totaux. Configuration simulée, aucun parcours sur instance.
- PHPStan : non exécuté, aucun outil/configuration identifié dans le module.

| Dolibarr | Source figée | Précision native et scénarios REP |
|---|---|---|
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` | Réussite, PHP 8.5.7, simulation |
| 21.0.0 | `fd970b582a4d8c5779a2958a4e9f4fce225cf085` | Réussite, PHP 8.5.7, simulation |
| 22.0.0 | `49b9a6d19f3deb6d410c0e9b3310e95be4ea7710` | Réussite, PHP 8.5.7, simulation |
| 23.0.0 | `57a1f05d490a7a80944a8232e9c613e6556d2704` | Réussite, PHP 8.5.7, simulation |
| 24.0.0 | `769c7db907099643558e77d7002c109cfda919e5` | Réussite, PHP 8.5.7, simulation |
| 25 alpha | `f6227503c4d08f38050cebb9ab73ba051710f638` | Réussite, PHP 8.5.7, simulation |

Le fichier lu est `htdocs/core/lib/functions.lib.php` ; les symboles exécutés sont `price2num`, `getDolGlobalInt`, `getDolGlobalString` et `dol_strlen`. Ces résultats ne prouvent pas une compatibilité de tous les parcours du module ni celle d’une future v25 finale.

## Recette réelle sur develop — 2026-10-11

Instance `develop.lesmetiersdubatiment.fr`, Dolibarr `25.0.0-alpha`, PHP `8.3.35`, Multicompany `24.0.2`, compte administrateur connecté, entité TEST 1 (1). Le SHA du core réellement exécuté n'a pas été relevé : cette instance ne doit pas être assimilée à la révision alpha figée des simulations.

Déploiement via le Git Control cPanel du checkout directement servi : branche `fix/agents-compliance`, commit fonctionnel testé `cc7077bd8ed88c0627b579853bb0225bd9b01469`, version du descripteur et de l'onglet À propos `1.0.1`. Le checkout précédent utilisait `1.0.1-fix-cxml` au commit `fb813e235366c76a19e770dd8df82ba52a987244` et affichait `1.0.0`. Aucun core modifié, aucune release ni fusion de PR.

| Scénario exécuté | Résultat observé |
|---|---|
| Découverte et vignette v25 | Image `/custom/lmdbwurthpunchout/img/lmdbwurthpunchout.png` chargée, largeur naturelle non nulle |
| Activation, désactivation puis réactivation en TEST 1 | Succès visible sans erreur fatale ni SQL dans l'interface ; les 22 réglages non secrets relevés sont identiques avant/après |
| Réglages et À propos | Pages accessibles, PHP/Dolibarr détectés et version 1.0.1 affichés |
| Pagination des sessions | Défaut reproduit : passage 20→50 avec filtres vides envoyés à `-1`, liste vide ; après normalisation native des valeurs vides, 20 puis 50 lignes visibles, retour à 20 |
| Création d'une donnée de recette | Commande fournisseur WURTH `(PROV23)`, id 23, brouillon sans ligne, note interne `RECETTE-20261011-WURTH` |
| Connexion cXML et catalogue WURTH | Catalogue authentifié ouvert dans la modale depuis ce brouillon |
| Panier externe de recette | Article 05561173 ajouté ; panier affiche une unité de conditionnement de 100 pièces, 31,23 € HT. Aucune commande fournisseur passée chez WURTH |
| Transfert du panier et import Dolibarr | Non validés : navigation du panier intégré sans résultat exploitable, puis fenêtre dédiée refusée par la politique de sécurité du navigateur pour URL invalide. Aucun contournement effectué |
| Import avec session inexistante | Refus « Session Punchout introuvable » ; ce contrôle ne constitue pas une preuve du rejet CSRF sur une session valide |

État restitué : entité TEST 1, module désactivé comme à l'arrivée, réglages contrôlés conservés. Les droits administrateur automatiquement attribués par l'activation native n'ont pas été supprimés ni réattribués manuellement ; leur état initial exhaustif n'a pas été relevé. Le brouillon de recette et le panier externe restent conservés, sans achat ni import métier. Aucun produit existant n'a été modifié par cette recette WURTH.

Restent non exécutés : import final, REP réelle, refus CSRF sur une session valide, replay après import, concurrence et erreurs de commit sur la connexion native, utilisateur standard/externe avec droits partiels, isolation WURTH entre deux entités et PHP 8.0. Le cycle natif sur cette instance n'est pas une validation exhaustive de la migration des attributions ni de toutes les versions v20–v25. PHPStan reste non exécuté faute d'outillage. Les effets externes du panier ne sont pas annulés par la restauration de configuration.
