# Validation de la préparation 1.0.1

Contrôles du 2026-10-11, PHP 8.5.7. Aucun déploiement ni appel WURTH effectué.

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

Restent à exécuter sur instance : PHP 8.0, MySQL/MariaDB, activation/désactivation/réactivation, droits natifs réellement attribués et restrictions d’accès à la commande, deux entités Multicompany, refus POST sans CSRF, retour OCI/cXML puis confirmation et retour sur commande, pagination navigateur, import simultané et erreurs de commit avec la connexion native. Aucun de ces contrôles n’est présenté comme réussi.
