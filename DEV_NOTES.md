# Documentation Développeur – Plugin DSI Location

## 1. Architecture générale

- **Fichier principal** : `dsi-location.php`
  - Bootstrap du plugin, inclut tous les handlers, pages admin, helpers, vues, hooks WooCommerce.

- **Logique métier** :
  - `includes/booking-calendar-handler.php` : gestion des réservations, calendrier, endpoints AJAX, helpers de réservation.
  - `includes/maintenance-handler.php` : gestion des périodes de maintenance (CRUD, helpers).
  - `includes/product-meta.php` : champs personnalisés WooCommerce pour les produits louables.
  - `includes/admin-pages.php` : toutes les pages admin (réglages, maintenance, retours), notifications, pagination.
  - `includes/utils.php` : helpers réutilisables (formatage, validation, etc.).
  - `includes/frontend-hooks.php` : hooks WooCommerce pour l’affichage front (formulaires, calendriers, etc.).
  - `includes/woocommerce-hooks.php` : hooks WooCommerce pour la gestion des réservations dans le panier, la commande, le prix, etc.
  - `includes/install.php` : création des tables à l’activation du plugin.

- **Vues** :
  - `views/` : pages principales (formulaire de réservation, admin maintenance, admin retours, etc.).
  - `views/partials/` : composants réutilisables (notifications, etc.).

- **Assets** :
  - `js/` : scripts front (`dsi-front.js`), scripts admin (`dsi-back.js`).
  - `css/` : styles front et admin.

---

## 2. Conventions et bonnes pratiques

- **Séparation logique/vue** :
  - Toute la logique (traitement, accès DB, validation) reste dans les handlers PHP.
  - Les vues ne font qu’afficher, avec les variables préparées par la logique.
  - Les notifications passent toujours par la vue partielle `views/partials/notice.php`.

- **Helpers** :
  - Centraliser les fonctions utilitaires dans `includes/utils.php`.
  - Exemples :
    - `dsi_format_date_fr($date)` : formate une date au format français.
    - `dsi_check_required_fields($fields, $data)` : vérifie la présence de champs obligatoires dans un tableau.

- **AJAX** :
  - Tous les endpoints AJAX sont déclarés dans les handlers (`booking-calendar-handler.php`, etc.).
  - Toujours vérifier le nonce (`check_ajax_referer`).
  - Toujours valider et assainir les données reçues.

- **Pages admin** :
  - Centralisées dans `includes/admin-pages.php`.
  - Les vues sont dans `views/`.
  - Utiliser les helpers pour la pagination, la validation, etc.

- **Front** :
  - Formulaires et calendriers générés par les hooks WooCommerce et les vues.
  - Scripts front dans `js/dsi-front.js`.

---

## 3. Endpoints AJAX principaux

- `dsi_submit_reservation` : soumission d’une réservation (POST, AJAX, handler PHP).
- `dsi_get_calendar_events` : récupération des événements à afficher dans le calendrier (réservations, maintenances).
- `dsi_get_user_reservations` : liste des réservations de l’utilisateur (pour la modale front).
- `dsi_cancel_reservation` : annulation d’une réservation par l’utilisateur.
- `dsi_check_reservation_availability` : vérification de la disponibilité d’un créneau avant ajout au panier.

---

## 4. Notifications

- Utiliser la vue partielle `views/partials/notice.php` pour toutes les notifications (succès, erreur, info).
- Exemple d’utilisation :
  ```php
  $type = 'success';
  $message = 'Action réussie !';
  include dirname(__DIR__) . '/views/partials/notice.php';
  ```

---

## 5. Ajout de helpers

- Ajouter toute fonction utilitaire dans `includes/utils.php`.
- Documenter chaque helper avec un commentaire PHPDoc.
- Exemples fournis :
  - `dsi_format_date_fr($date)`
  - `dsi_check_required_fields($fields, $data)`

---

## 6. Contribution et évolution

- **Ajouter une nouvelle page admin** :
  - Ajouter la logique dans `admin-pages.php`.
  - Créer la vue dans `views/`.
  - Ajouter le menu via `add_submenu_page`.

- **Ajouter un nouveau composant réutilisable** :
  - Créer la vue dans `views/partials/`.
  - L’inclure dans les pages ou handlers concernés.

- **Ajouter un nouveau handler AJAX** :
  - Déclarer le endpoint dans le handler concerné.
  - Toujours valider et assainir les données.
  - Toujours vérifier le nonce.

- **Mettre à jour la documentation** :
  - Ajouter toute nouvelle convention, helper, ou point d’attention dans ce fichier.

---

## 7. Points d’attention

- Toujours séparer la logique et la vue.
- Toujours valider et assainir les données utilisateur.
- Toujours utiliser les helpers pour éviter la duplication.
- Toujours documenter les helpers et les conventions.

---

**Pour toute évolution, suivre ces conventions pour garantir la robustesse et la maintenabilité du plugin.** 

## [2024-xx-xx] Vérification des chevauchements maintenance/réservation
- Il n'est plus possible d'ajouter une période de maintenance si une réservation existe déjà sur la même période (même produit et unité).
- La vérification est faite côté admin (formulaire classique et AJAX).
- Fonction utilitaire ajoutée : `dsi_location_is_in_reservation` dans `includes/maintenance-handler.php`. 