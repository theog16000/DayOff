# 📅 DayOff - Gestionnaire de Congés WordPress

DayOff est un plugin WordPress léger permettant de gérer les demandes de congés des employés. Il inclut un système de soumission de demandes, un dashboard administrateur pour la validation, et une gestion automatique des quotas.

## 🚀 Fonctionnalités

- **Installation Automatique** : Création des tables SQL et des pages nécessaires dès l'activation.
- **Système de Login Dédié** : Une page de connexion personnalisée hors du back-office WP.
- **Dashboard Collaborateur** : Soumission de demandes et consultation du solde.
- **Interface Admin** : Validation/Refus des congés et historique détaillé par utilisateur.
- **Notifications** : Système d'emails (via `wp_mail`).

## 🛠️ Installation

1. **Téléchargement** : Clonez ce dépôt dans le dossier `/wp-content/plugins/` de votre installation WordPress.
   ```bash
   git clone [https://github.com/votre-pseudo/mon-plugin-dayoff.git](https://github.com/votre-pseudo/mon-plugin-dayoff.git)
   ```
2. **OU ACTIVATION** : Allez dans l'administration WordPress > **Extensions** et activez "DayOff".
3. **Tables SQL** : Le plugin créera automatiquement les tables `wp_conges_demandes` et `wp_conges_modifications`.
4. **Pages** : Mettre les slugs `/connexion` et `/dashboard-conges` sur vos pages.

[IMPORTANT] : Après l'activation, rendez vous dans `**Règlages > Permaliens**` et cliquez sur **Enregistrer les modifications** pour éviter les erreurs 404 sur les nouvelles pages.

**Plugin réalisé par Théo G.**
