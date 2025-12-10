# Samapiece - Plateforme de Récupération de Documents Officiels

Une application web complète en Python pour aider les gens à récupérer leurs documents officiels perdus.

## 📋 Description

Samapiece est une plateforme permettant de:
- **Déclarer des documents trouvés** (carte d'identité, passeport, permis, etc.)
- **Rechercher des documents perdus** en utilisant les informations personnelles
- **Connecter** la personne qui a trouvé le document avec le propriétaire

## 🛠️ Technologie

- **Framework**: Flask (Python 3)
- **Base de données**: MongoDB (NoSQL)
- **ODM**: PyMongo
- **Templates**: Jinja2
- **Frontend**: HTML5 + CSS3 (minimaliste)

## 📁 Structure du Projet

```
samapiece/
├── app.py                 # Application Flask principale
├── requirements.txt       # Dépendances Python
├── templates/             # Templates HTML
│   ├── base.html         # Template de base (layout)
│   ├── home.html         # Page d'accueil
│   ├── register.html     # Formulaire d'enregistrement
│   ├── declare.html      # Formulaire de déclaration
│   ├── search.html       # Formulaire de recherche
│   ├── results.html      # Page de résultats
│   ├── success.html      # Page de succès
│   └── error.html        # Page d'erreur
└── uploads/              # Dossier pour les images uploadées
```

## 🚀 Installation et Démarrage

### 1. Prérequis

- Python 3.7+
- MongoDB (version 4.0+)
- pip (gestionnaire de paquets Python)

### 2. Installation de MongoDB

#### Sur Windows:
1. Téléchargez MongoDB depuis https://www.mongodb.com/try/download/community
2. Installez MongoDB Community Edition
3. Assurez-vous que MongoDB s'exécute en tant que service Windows

Pour vérifier que MongoDB fonctionne:
```powershell
# Ouvrez une invite de commandes et tapez:
mongosh
```

### 3. Clonage / Configuration du Projet

```powershell
# Accédez au répertoire du projet
cd C:\Users\v.mendes\Desktop\Guisnako\samapiece

# Créez un environnement virtuel Python (optionnel mais recommandé)
python -m venv venv

# Activez l'environnement virtuel
# Sur Windows (PowerShell):
.\venv\Scripts\Activate.ps1

# Sur Windows (CMD):
venv\Scripts\activate.bat
```

### 4. Installation des Dépendances

```powershell
pip install -r requirements.txt
```

### 5. Lancement de l'Application

```powershell
python app.py
```

Vous devriez voir:
```
============================================================
Samapiece - Plateforme de récupération de documents
============================================================
L'application démarre sur http://localhost:5000
Appuyez sur Ctrl+C pour arrêter le serveur
============================================================
```

### 6. Accès à l'Application

Ouvrez votre navigateur et allez à:
```
http://localhost:5000
```

## 📚 Utilisation

### Flux d'utilisation typique:

1. **Créer un compte** (`/register`)
   - Entrez votre nom, prénom
   - Fournissez un email ou un numéro de téléphone
   - Sélectionnez optionnellement votre code pays

2. **Déclarer un document** (`/declare`)
   - Sélectionnez le type de document
   - Entrez les informations visibles sur le document (nom, prénom, date/lieu de naissance)
   - Entrez votre numéro de téléphone de contact
   - Téléchargez une photo du document

3. **Rechercher un document** (`/search`)
   - Entrez vos informations personnelles
   - Consultez les résultats si le document a été trouvé
   - Contactez directement la personne qui l'a trouvé

## 📊 Modèle de Données MongoDB

### Collection `users`:
```json
{
  "_id": ObjectId,
  "nom": "string",
  "prenom": "string",
  "email": "string ou null",
  "telephone": "string ou null",
  "code_pays": "string ou null",
  "date_creation": "datetime"
}
```

### Collection `documents`:
```json
{
  "_id": ObjectId,
  "type_piece": "string",
  "nom": "string",
  "prenom": "string",
  "date_naissance": "string",
  "lieu_naissance": "string",
  "telephone_declarant": "string",
  "photo_path": "string",
  "user_id": ObjectId,
  "date_declaration": "datetime"
}
```

## 🔒 Sécurité

- Les fichiers uploadés sont validés (extensions d'image uniquement)
- Les noms de fichiers sont sécurisés avec `secure_filename()`
- Limite de taille de fichier: 16 MB
- Session utilisateur avec Flask (simple)

## 🔧 Configuration Avancée

### Modifier le port Flask:
Dans `app.py`, ligne finale:
```python
app.run(debug=True, host='localhost', port=5000)  # Changez 5000 par le port souhaité
```

### Modifier l'URI MongoDB:
Dans `app.py`, ligne ~40:
```python
MONGODB_URI = 'mongodb://localhost:27017/'  # Modifiez si MongoDB est sur un autre serveur
```

### Modifier le secret de session:
Pour la production, changez cette ligne dans `app.py`:
```python
app.secret_key = 'votre_cle_secrete_ici_changez_moi'  # Utilisez une clé forte
```

## 🐛 Dépannage

### Erreur: "Connexion à MongoDB échouée"
- Vérifiez que MongoDB est en cours d'exécution
- Assurez-vous que MongoDB écoute sur `localhost:27017`
- Vérifiez l'URI MongoDB dans le code

### Erreur: "Module not found"
- Assurez-vous d'avoir activé l'environnement virtuel
- Réinstallez les dépendances: `pip install -r requirements.txt`

### Les images uploadées ne s'affichent pas
- Vérifiez que le dossier `uploads/` existe
- Vérifiez les permissions du dossier
- Vérifiez le chemin dans les templates

## 📝 Notes de Développement

- Le code inclut des commentaires en français pour faciliter la compréhension
- L'authentification est très simple (ID stocké en session) - à améliorer pour la production
- La validation des données est basique - ajouter plus de validation pour la production
- CSS est inlinisé dans `base.html` pour une seule dépendance fichier

## 📄 Licence

Projet éducatif - Libre d'utilisation

## 👨‍💻 Auteur

Généré pour la plateforme Samapiece

---

**Questions ou améliorations?** Modifiez le code source et adaptez-le à vos besoins!
