# Pour une API Symfony 6.2 + front React, la solution la plus propre et standard c’est :
Symfony Security + LexikJWTAuthenticationBundle
(les “middlewares” sont gérés par les firewalls + l’authenticator JWT)
1. Le client (React, Postman…) envoie son email + password sur /api/login.
2. Symfony vérifie le mot de passe.
3. Si OK → il renvoie un token JWT.
4. Pour chaque requête suivante, le client envoie le header : Authorization: Bearer <le_token_jwt>
5. Un middleware de sécurité (firewall + authenticator) vérifie le token avant d’exécuter ton contrôleur.

# User = L’utilisateur technique de l’API (Authentification / Sécurité)
User est l’entité que Symfony utilise obligatoirement pour :
l’authentification (login)
la génération et validation du JWT
la gestion des rôles (ROLE_USER, ROLE_ADMIN…)
la sécurité globale de ton API
👉 User sert uniquement à permettre l’accès sécurisé à ton API.
👉 Il est utilisé par le security.yaml + LexikJWT.

"token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE3NjUxMjQ4NDYsImV4cCI6MTc2NTEyODQ0Niwicm9sZXMiOlsiUk9MRV9VU0VSIl0sInVzZXJuYW1lIjoiYWRtaW5AZ21haWwuY29tIn0.wukaDvM3PSgYkJwKlKiirhN1lsMP_J3WH1-RBOcVV5AEgtTYOOj2ypNVznH8fyO3wVPFkk5Wff1D0UyEp9yZRFlpK9mG_EIlUbmuioNjWndZQDY8yUT_wOjPj0ziPDfR2uD25NaEXM7kE_bpEHEGIVZo_bowM6ASboHReJOB8HlHqVg_a9keqX_k86_Aj2577pLfk7-gcf_IlV_9H88pfLrZ12L1LP-VlBNMbBfxpLDL8_VjJjuhYh_FK_JhcDxAwcIIazGTI3FzPkJnZaNiI6yEYfdLANstu_OZ-jVW-Jm4cq1hEGWq59DWmj396FS6BYRYY7oVLFyG15cwmmkNrg"

# Résumé du fonctionnement du JWT dans notre API Symfony

Le système d’authentification utilise LexikJWTAuthenticationBundle, basé sur une paire de clés RSA (privée/publique) pour signer et vérifier les tokens JWT. Lors de l’inscription, le mot de passe est haché puis stocké en base. La connexion via /api/login génère un JWT signé, renvoyé au client. Toutes les routes protégées sous /api/** nécessitent ensuite l’envoi du token dans le header Authorization: Bearer. Le firewall vérifie automatiquement le token et reconstruit l'utilisateur avant l’exécution de la route.

# 5) Processus expliqué simplement (Cas A)

Inscription (public)
➡️ React/Postman envoie nom/email/password vers POST /api/register
✅ Symfony crée User (pour la sécurité) + Client (profil)

Connexion (public)
➡️ envoie email/password vers POST /api/login
✅ Symfony renvoie un JWT token

Appels API (protégés)
➡️ chaque requête vers /api/** doit envoyer :
Authorization: Bearer <token>
✅ Symfony laisse passer si token OK



Mot de passe des appli : ahrmntiaoybxxyvg



### Rôle de VichUploaderBundle
Le bundle a été installé et configuré pour une gestion “standardisée” des fichiers (mappings, destination, uri_prefix).
Dans cette version, l’upload est réalisé via un contrôleur API (gestion manuelle), ce qui permet un contrôle fin des validations et une intégration simple avec Postman/Front.
L’activation complète de Vich au niveau entity (annotations UploadableField) est possible en évolution.

Tu peux inclure cette phrase :
“Conformément aux bonnes pratiques, seul le chemin du fichier est persisté en base ; le binaire reste sur le système de fichiers afin d’éviter l’usage de BLOB, coûteux en performance et en maintenance.”