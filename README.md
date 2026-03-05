# 🌴 DayOff - Gestionnaire de Congés pour WordPress

**DayOff** est une solution légère et moderne conçue pour simplifier la gestion des congés au sein d'une organisation. Ce plugin permet aux collaborateurs de soumettre leurs demandes en ligne et aux administrateurs de les gérer via une interface dédiée.

---

## ✨ Fonctionnalités

### 👤 Interface Collaborateur
- **Tableau de Bord interactif** : Visualisation des soldes (CP, RTT, Récupération, Maladie).
- **Soumission AJAX** : Formulaire dynamique pour poser des congés sans rechargement de page.
- **Calendrier Personnel** : Vue mensuelle (FullCalendar) pour suivre ses absences.
- **Gestion du Profil** : Mise à jour de l'email, du nom et du mot de passe en autonomie.

### 👑 Interface Administrateur
- **Gestion des Demandes** : Interface de validation (Approuver / Refuser).
- **Suivi des Statuts** : Mise à jour instantanée du statut des demandes.

---

## 🚀 Guide d'Installation Complète (Pas à Pas)

### 1. Installation des fichiers
1. Téléchargez le dossier du plugin `dayoff`.
2. Déposez-le dans le répertoire `/wp-content/plugins/` de votre serveur.
3. Allez dans votre administration WordPress > **Extensions** et cliquez sur **Activer** sous le plugin "DayOff".

### 2. Configuration de la Base de Données
Le plugin utilise une table personnalisée. Connectez-vous à votre **phpMyAdmin**, sélectionnez votre base de données et exécutez la requête SQL suivante :

```sql
CREATE TABLE IF NOT EXISTS `wp_conges_demandes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `type_conge` varchar(50) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `statut` varchar(20) DEFAULT 'en_attente',
  `commentaire` text,
  `date_creation` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
