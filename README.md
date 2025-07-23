# DSI Location – Documentation Utilisateur

## Présentation

DSI Location est un plugin WordPress/WooCommerce permettant la gestion de la location de matériel avec calendrier, réservation, gestion de la maintenance et des retours, notifications, et interface utilisateur moderne.

---

## 1. Installation

1. Téléchargez ou clonez le plugin dans le dossier `wp-content/plugins/dsi-location`.
2. Activez le plugin depuis l’interface d’administration WordPress (Extensions > Extensions installées).
3. Vérifiez que WooCommerce est bien activé.

---

## 2. Configuration (Admin)

### a) Réglages généraux
- Accédez à **DSI Location > Réglages** dans le menu admin.
- Configurez les délais d’annulation par catégorie de produit.

### b) Gestion des produits louables
- Ajoutez ou éditez un produit WooCommerce.
- Renseignez les champs personnalisés (prix demi-journée, journée, week-end, montant de caution, nombre d’unités louables).

### c) Maintenance
- Accédez à **DSI Location > Maintenance**.
- Ajoutez une période de maintenance pour un produit/unité (dates de début/fin).
- Visualisez le calendrier de maintenance.
- Marquez une maintenance comme terminée si besoin.

### d) Gestion des retours
- Accédez à **DSI Location > Retours**.
- Filtrez les retours par produit.
- Marquez un produit comme retourné.
- Utilisez la pagination pour naviguer dans la liste.

---

## 3. Utilisation côté client

### a) Réservation d’un produit
- Sur la fiche produit, sélectionnez les dates de location via le calendrier.
- Remplissez le formulaire (date de début, heure, multi-jour si besoin).
- Cliquez sur « Réserver ».
- Si le créneau est disponible, la réservation est ajoutée au panier WooCommerce.
- Finalisez la commande comme pour un achat classique.

### b) Visualisation et annulation de ses réservations
- Sur la fiche produit, cliquez sur « Voir mes réservations ».
- Une modale affiche la liste de vos réservations pour ce produit/unité.
- Vous pouvez annuler une réservation si besoin.

### c) Notifications
- Les messages de succès, d’erreur ou d’information s’affichent automatiquement après chaque action (réservation, annulation, etc.).

---

## 4. Gestion des maintenances (Admin)
- Un produit en maintenance ne peut pas être réservé sur la période concernée.
- Les périodes de maintenance sont visibles dans le calendrier admin et côté client (en bleu).

---

## 5. FAQ

**Q : Peut-on réserver plusieurs unités d’un même produit ?**
- Oui, chaque unité a son propre calendrier et formulaire de réservation.

**Q : Que se passe-t-il si deux clients essaient de réserver le même créneau ?**
- Le système bloque automatiquement les doublons. Si le créneau est déjà réservé ou en maintenance, la réservation est refusée.

**Q : Peut-on personnaliser les messages ou les couleurs du calendrier ?**
- Oui, en modifiant les fichiers JS/CSS du plugin.

**Q : Comment ajouter un nouveau type de notification ?**
- Utilisez la vue partielle `views/partials/notice.php`.

**Q : Les réservations sont-elles liées à WooCommerce ?**
- Oui, chaque réservation validée est associée à une commande WooCommerce.

---

## 6. Support

Pour toute question ou évolution, contactez l’administrateur du site ou le développeur du plugin.

---

**Merci d’utiliser DSI Location !** 