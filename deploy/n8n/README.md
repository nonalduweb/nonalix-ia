# Raccorder un agent Nonalix IA à n8n

Six outils de l'agent délèguent leur exécution à un scénario n8n : `create_prospect`,
`book_appointment`, `send_email`, `generate_quote`, `check_order_status`, `send_document`.

L'entreprise renseigne l'URL de son propre webhook dans **Configuration › Agent IA ›
Réglages avancés**. Chaque entreprise pointe vers **son** n8n : rien n'est partagé.

`dispatcher-modele.json` est un scénario complet, importable tel quel, qui sert de
point de départ. Il route les six actions et n'en raccorde aucune à un système métier
— c'est à vous de remplacer les branches par vos intégrations.

---

## Le piège à connaître avant tout : `arguments`

Nonalix IA envoie les paramètres de l'outil dans une clé nommée `arguments`. Or
**l'évaluateur d'expressions de n8n refuse cet identifiant**, mot réservé de
JavaScript :

```
Cannot access "arguments" due to security concerns
```

Écrire `{{ $json.body.arguments.name }}` échoue donc, et de la pire façon : le nœud
tombe en erreur, et le webhook répond **200 avec un corps vide**. Nonalix IA, qui tient
tout code 2xx pour un succès, enregistre alors un rendez-vous ou un devis qui n'a
jamais eu lieu.

La parade est le premier nœud du modèle, **Normaliser payload**, qui renomme la clé en
`args` une fois pour toutes. Notation entre crochets obligatoire, y compris à
l'intérieur d'un nœud Code :

```js
const b = $json['body'] ?? {};
const a = b['arguments'] ?? {};   // et non b.arguments
return [{ json: { action: b['action'], args: a, /* … */ } }];
```

Tous les nœuds suivants lisent `{{ $json.args.… }}`, sans jamais rencontrer le mot
interdit.

---

## Ce que Nonalix IA envoie

`POST` sur votre URL de webhook, corps JSON :

```json
{
  "action": "create_prospect",
  "tenant_id": "019f9e8e-fd50-7060-af59-fb94cd87b992",
  "conversation_id": "019f9e90-1a2b-7000-8000-000000000000",
  "contact_id": "019f9e90-3c4d-7000-8000-000000000000",
  "wa_id": "2250700000000",
  "arguments": { "name": "Awa Koné", "need": "Devis toiture", "budget": "2 000 000 FCFA" }
}
```

`wa_id` est nul pour une conversation issue du widget web. Le contenu d'`arguments`
dépend de l'outil — il correspond aux paramètres déclarés dans sa définition, visibles
depuis la page de l'agent.

Délai d'attente : **10 secondes**. Un scénario plus lent doit répondre immédiatement et
poursuivre son travail en arrière-plan.

## Ce que Nonalix IA attend en retour

| Cas | Code HTTP | Corps |
|---|---|---|
| Action réalisée | `2xx` | `{"message": "…"}` |
| Action impossible | tout sauf `2xx` | libre |

Le `message` est **relu par le modèle, pas par un humain** : il doit être factuel,
compact, et peut contenir une consigne (« Confirme-le au client »). Les clés `message`
et `output` sont acceptées ; sans l'une des deux, un texte générique est utilisé.

### Ne jamais répondre 2xx pour une action non réalisée

C'est la règle la plus importante. `book_appointment` et `generate_quote` inscrivent un
marqueur sur le prospect (`appointment_booked`, `quote_sent`) que le tableau de bord
commercial compte. Un 2xx de complaisance gonfle ces chiffres avec des actions
fictives.

Le modèle répond donc **501** pour toute action non raccordée. L'agent l'annonce alors
honnêtement au client et propose un conseiller humain — ce qui est le comportement
souhaitable.

---

## Installer le modèle

1. Dans n8n : **Workflows › Import from File**, choisir `dispatcher-modele.json`.
2. Remplacer les valeurs `REMPLACER…` : identifiants Telegram et SMTP, identifiant de
   discussion, adresse d'expédition. Supprimer les branches dont vous n'avez pas besoin.
3. Activer le scénario, puis copier l'URL de production du nœud **Webhook Nonalix IA**
   (de la forme `https://votre-n8n/webhook/nonalix-ia`).
4. La coller dans **Configuration › Agent IA › Réglages avancés › URL du webhook n8n**.
5. Activer les outils correspondants dans la même page — **uniquement ceux que vous
   avez réellement raccordés**. Un agent ne doit pas promettre ce qu'il ne peut tenir.

### Import en ligne de commande

```bash
docker cp dispatcher-modele.json <conteneur-n8n>:/tmp/wf.json
docker exec <conteneur-n8n> n8n import:workflow --input=/tmp/wf.json
docker exec <conteneur-n8n> n8n update:workflow --id=<id> --active=true
docker restart <conteneur-n8n>
```

L'activation par la ligne de commande n'est prise en compte qu'au redémarrage. Si le
chemin répond `404 … is not registered` après un réimport, une inscription périmée
subsiste : désactivez, redémarrez, réactivez, redémarrez.

---

## Vérifier

```bash
# Action non raccordée : doit répondre 501
curl -i -X POST -H 'Content-Type: application/json' \
  -d '{"action":"book_appointment","arguments":{}}' \
  https://votre-n8n/webhook/nonalix-ia

# Action raccordée : doit répondre 200 et un {"message": …} non vide
curl -i -X POST -H 'Content-Type: application/json' \
  -d '{"action":"create_prospect","arguments":{"name":"Test","need":"Vérification"}}' \
  https://votre-n8n/webhook/nonalix-ia
```

Un **200 au corps vide** signale presque toujours qu'un nœud est tombé en erreur avant
le nœud de réponse. Ouvrez l'exécution correspondante dans n8n : c'est très souvent le
piège `arguments` décrit plus haut.
