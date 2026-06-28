# ChangeLog

## Unreleased

- Correction de la structure `PunchOutSetupRequest` cXML : `SharedSecret` est désormais porté par `Sender/Credential`.
- Ajout de la prise en charge des retours panier cXML en `cXML-base64`.
- Ajout d’identifiants `Sender` cXML optionnels avec repli sur les identifiants client.
- Journalisation assainie des rejets de setup cXML.
- Déclaration explicite du document cXML sortant en version `1.2.008` avec DTD, pour compatibilité avec l’endpoint WURTH.
- Ajout du parsing cXML complet du panier : frais de port, total, taxe, adresse de livraison et métadonnées de lignes.
- Ajout de l’import optionnel des frais de port cXML positifs comme ligne de commande fournisseur.
- Ajout d’un fallback cXML WURTH optionnel pour déduire les frais annexes depuis l’écart entre la taxe d’en-tête et les taxes des lignes lorsque `Shipping/Money` vaut zéro.
- Ajout de l’import REP `REP Taxe n/w` comme ligne séparée avec barème par référence fournisseur WURTH.
- Ajout des migrations idempotentes pour les nouvelles colonnes de stockage cXML et le barème REP WURTH.

## 1.0.0 - 2026-06-25

- Création du module externe `lmdbwurthpunchout`.
- Alignement des numéros de permissions sur le modèle `$this->numero * 100 + $r`.
