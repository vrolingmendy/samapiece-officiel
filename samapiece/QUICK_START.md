# 🚀 GUIDE DE DÉMARRAGE RAPIDE - SAMAPIECE (Windows)

## Étape 1: Vérifier que MongoDB s'exécute

MongoDB doit être en cours d'exécution avant de lancer l'application.

### Option 1: MongoDB comme service Windows (recommandé)
Si MongoDB a été installé comme service, il devrait démarrer automatiquement.

Pour vérifier que MongoDB s'exécute:
```powershell
# Ouvrez PowerShell et lancez:
mongosh
```

Si vous voyez l'invite `>`, MongoDB fonctionne. Tapez `exit` pour quitter.

### Option 2: Démarrer MongoDB manuellement
Si MongoDB n'est pas un service Windows, démarrez-le manuellement:

```powershell
# Remplacez le chemin avec l'installation réelle de MongoDB
"C:\Program Files\MongoDB\Server\7.0\bin\mongod.exe"
```

Laissez cette fenêtre ouverte - MongoDB s'exécute en arrière-plan.

---

## Étape 2: Installer les dépendances Python

```powershell
# Accédez au dossier du projet
cd "C:\Users\v.mendes\Desktop\Guisnako\samapiece"

# (Optionnel) Créez un environnement virtuel
python -m venv venv

# Activez l'environnement virtuel
.\venv\Scripts\Activate.ps1

# Installez les dépendances
pip install -r requirements.txt
```

---

## Étape 3: Lancer l'application

```powershell
# Assurez-vous d'être dans le dossier samapiece
cd "C:\Users\v.mendes\Desktop\Guisnako\samapiece"

# Lancez l'application
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

---

## Étape 4: Accédez à l'application

Ouvrez votre navigateur et allez à:
```
http://localhost:5000
```

---

## 🎯 Premier test rapide

1. Cliquez sur **"Créer un compte"**
2. Remplissez le formulaire:
   - Nom: `Martin`
   - Prénom: `Alice`
   - Email: `alice@example.com`
   - Pays: `+33` (optionnel)
3. Cliquez sur **"Créer mon compte"**

Vous devriez voir un message de succès! ✓

---

## 📸 Tester la déclaration

1. Cliquez sur **"Déclarer un document trouvé"**
2. Sélectionnez l'utilisateur créé précédemment
3. Remplissez les champs (utilisez des données fictives)
4. Pour la photo, utilisez une image PNG ou JPG quelconque
5. Cliquez sur **"Déclarer le document"**

---

## 🔍 Tester la recherche

1. Cliquez sur **"Rechercher un document"**
2. Entrez les informations que vous avez saisies lors de la déclaration
3. Cliquez sur **"Rechercher"**

Vous devriez voir le document trouvé!

---

## 🛑 Arrêter l'application

Appuyez sur **Ctrl+C** dans la fenêtre PowerShell où s'exécute l'application.

---

## ⚠️ Problèmes courants

### "ModuleNotFoundError: No module named 'flask'"
→ Vous n'avez peut-être pas activé l'environnement virtuel ou les dépendances ne sont pas installées.
```powershell
.\venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

### "Erreur de connexion à MongoDB"
→ Vérifiez que MongoDB s'exécute:
```powershell
mongosh
```

### Le port 5000 est déjà utilisé
→ Modifiez le port dans `app.py` (ligne finale):
```python
app.run(debug=True, host='localhost', port=5001)  # Utilise le port 5001 à la place
```

### Les images ne s'affichent pas
→ Le dossier `uploads/` doit exister (il est déjà créé). Vérifiez les permissions.

---

## 📚 Fichiers importants

- **app.py**: L'application Flask principale
- **templates/**: Les pages HTML
- **uploads/**: Où sont stockées les images uploadées
- **requirements.txt**: Les dépendances Python
- **README.md**: Documentation complète

---

## 🎓 Structure des routes

| Route | Méthode | Description |
|-------|---------|-------------|
| `/` | GET | Page d'accueil |
| `/register` | GET/POST | Créer un compte |
| `/declare` | GET/POST | Déclarer un document |
| `/search` | GET/POST | Rechercher un document |

---

**Besoin d'aide?** Consultez README.md pour la documentation complète.

Bonne utilisation de Samapiece! 🎉
