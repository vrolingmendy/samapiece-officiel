# Samapiece - Plateforme de Récupération de Documents

## Vue d'ensemble

**Samapiece** est une application web moderne conçue pour facilitier la récupération de documents officiels perdus. Elle permet aux utilisateurs de:

- **Déclarer des documents trouvés** sur la plateforme
- **Signaler des documents perdus** via le système d'alertes
- **Consulter les documents disponibles** en tant que bien public
- **Gérer les alertes** pour recevoir des notifications en cas de correspondance

## Caractéristiques principales

### Pour les utilisateurs publics
✓ Créer un compte et se connecter  
✓ Déclarer des documents trouvés avec photo et informations  
✓ Créer des alertes pour rechercher des documents perdus  
✓ Consulter la liste des documents disponibles  
✓ Rechercher par type de document, nom, prénom, lieu  

### Pour les administrateurs
✓ Tableau de bord administratif sécurisé  
✓ Gestion des utilisateurs (activation, suspension)  
✓ Consultation des documents déclarés  
✓ Gestion des alertes avec correspondances automatiques  
✓ Visualisation des statistiques  
✓ Système d'anti-brute force (blocage après 5 tentatives)  

## Technologie utilisée

- **Framework web:** Flask 2.3.3
- **Base de données:** JSON (fichier data.json) avec fallback
- **Frontend:** HTML5, CSS3, JavaScript vanilla
- **Sécurité:** Hachage des mots de passe, CSRF/XSS protection, en-têtes de sécurité
- **Upload:** Optimisation automatique des images

## Installation et démarrage

### Prérequis
- Python 3.8+
- pip (gestionnaire de paquets Python)

### Étapes d'installation

1. **Cloner le projet**
```bash
git clone https://github.com/vrolingmendy/Guisnako-samapiec.git
cd samapiece
```

2. **Créer un environnement virtuel**
```bash
python -m venv .venv
```

3. **Activer l'environnement virtuel**
   - Sur Windows:
   ```bash
   .\.venv\Scripts\Activate.ps1
   ```
   - Sur macOS/Linux:
   ```bash
   source .venv/bin/activate
   ```

4. **Installer les dépendances**
```bash
pip install -r requirements.txt
```

5. **Lancer l'application**
```bash
python app.py
```

L'application sera disponible sur `http://localhost:5000`

## Configuration

### Variables d'environnement

Créez un fichier `.env` à la racine du projet:

```env
FLASK_ENV=development
FLASK_DEBUG=True
SMTP_SERVER=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=votre_email@gmail.com
SMTP_PASSWORD=votre_mot_de_passe_application
```

### Clé secrète

Modifiez la clé secrète dans `app.py`:
```python
app.secret_key = 'votre_cle_secrete_complexe'  # À changer en production
```

## Structure de la base de données

La base de données JSON contient les collections suivantes:

### Users (Utilisateurs)
```json
{
  "id": "uuid",
  "email": "user@example.com",
  "password_hash": "hash_du_mot_de_passe",
  "nom": "Dupont",
  "prenom": "Jean",
  "telephone": "+33123456789",
  "date_creation": "2025-12-11T10:00:00",
  "is_active": true
}
```

### Documents (Documents déclarés trouvés)
```json
{
  "id": "uuid",
  "type_piece": "Passeport",
  "nom": "Martin",
  "prenom": "Sophie",
  "date_naissance": "1990-05-15",
  "lieu_naissance": "Paris",
  "numero_piece": "AB123456",
  "date_declaration": "2025-12-11T10:00:00",
  "photo": "nom_fichier_photo",
  "utilisateur_id": "uuid_declarant",
  "code_recuperation": "ABCD-EFGH",
  "statut": "available"
}
```

### Alertes
```json
{
  "id": "uuid",
  "type_piece": "Permis",
  "nom": "Bernard",
  "prenom": "Pierre",
  "date_naissance": "1985-03-20",
  "lieu_naissance": "Lyon",
  "email": "pierre@example.com",
  "date_creation": "2025-12-11T10:00:00",
  "active": true
}
```

## Routes principales

### Routes publiques
- `GET /` - Page d'accueil
- `GET /documents` - Liste des documents disponibles
- `GET /search` - Recherche de documents
- `GET /register` - Inscription
- `POST /register` - Traitement de l'inscription
- `GET /login` - Connexion
- `POST /login` - Traitement de la connexion

### Routes admin
- `GET /admin/login` - Connexion administrateur
- `POST /admin/login` - Traitement de connexion admin
- `GET /admin/dashboard` - Tableau de bord
- `GET /admin/documents-perdus` - Gestion des documents
- `GET /admin/documents-perdus/<id>` - Détails document
- `GET /admin/alertes` - Gestion des alertes
- `GET /admin/alertes/<id>` - Détails alerte
- `GET /admin/users` - Gestion des utilisateurs
- `GET /admin/logout` - Déconnexion

## Sécurité

### Mesures de sécurité implémentées

1. **Anti-brute force**
   - Blocage du compte après 5 tentatives échouées
   - Verrouillage de 30 minutes

2. **Validation des mots de passe**
   - Minimum 8 caractères
   - Au moins une majuscule
   - Au moins un chiffre
   - Au moins un caractère spécial

3. **Entêtes de sécurité**
   - X-Frame-Options: SAMEORIGIN (protection clickjacking)
   - X-Content-Type-Options: nosniff
   - Content-Security-Policy: restrictive
   - CORS: configuré de manière sécurisée

4. **Protection CSRF**
   - Jetons CSRF générés pour chaque formulaire
   - Validation sur tous les formulaires

5. **Sanitisation des entrées**
   - Suppression des caractères dangereux
   - Validation des formats email

## Système d'alertes et correspondances

### Fonctionnement

1. L'utilisateur crée une **alerte** en recherchant un document
2. Quand un document correspondant est déclaré, le système détecte automatiquement la correspondance
3. L'administrateur peut consulter les alertes et voir les documents correspondants
4. L'administrateur peut contacter le déclarant pour faciliter la récupération

### Critères de correspondance

Un document correspond à une alerte si:
- Le type de pièce est identique
- Le nom et prénom correspondent (sensibilité casse)
- La date de naissance correspond (optionnel)
- Le lieu de naissance correspond (optionnel)

## Tests

Pour lancer les tests:
```bash
python -m pytest tests/
```

## Dépannage

### Erreur d'encodage UTF-8 sur Windows
Si vous rencontrez une erreur `UnicodeEncodeError`, définissez la variable d'environnement:
```bash
set PYTHONIOENCODING=utf-8
```

### Erreur de permission sur le dossier uploads
Assurez-vous que le dossier `uploads/` existe et que vous avez les permissions d'écriture:
```bash
mkdir uploads
chmod 755 uploads
```

### La base de données ne se charge pas
Vérifiez que le fichier `data.json` est valide:
```bash
python -m json.tool data.json
```

## Contribution

Les contributions sont bienvenues! Veuillez:

1. Faire un fork du projet
2. Créer une branche feature (`git checkout -b feature/AmazingFeature`)
3. Commiter vos changements (`git commit -m 'Add some AmazingFeature'`)
4. Pousser vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## Feuille de route

- [x] Authentification utilisateur
- [x] Déclaration de documents
- [x] Système d'alertes
- [x] Tableau de bord administrateur
- [x] Système anti-brute force
- [ ] Notifications par email automatiques
- [ ] Authentification à deux facteurs
- [ ] API REST pour applications mobiles
- [ ] Base de données PostgreSQL (migration)
- [ ] Intégration avec OCR pour reconnaissance de documents

## Support

Pour des questions ou rapports de bugs, veuillez ouvrir une issue sur:
https://github.com/vrolingmendy/Guisnako-samapiec/issues

## Auteurs

- **Vrolin Mendy** - Développement initial

## Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## Remerciements

- Merci à tous les contributeurs
- Merci à la communauté Flask
- Merci aux testeurs bêta

---

**Dernière mise à jour:** 11 décembre 2025  
**Version:** 1.0.0  
**Status:** En développement actif ✓
