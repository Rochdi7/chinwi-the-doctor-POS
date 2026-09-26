# Installation du scanner USB chez le client

Guide d'installation du lecteur de codes-barres **Honeywell Genesis 7580g**
sur le PC de caisse, et du voyant d'état affiché par le point de vente.

Destiné à l'installateur. Pour la caissière, une seule chose compte : le
voyant en haut du panier doit être **vert**.

---

## 1. Ce qu'il faut savoir avant de commencer

Le 7580g sort d'usine en **mode clavier USB** (HID). Dans ce mode il « tape »
le code-barres comme un clavier : c'est exactement ce que la caisse attend.

> ⚠️ **Ne pas suivre les guides qui demandent d'installer les pilotes
> *Serial Emulation / Virtual COM Port* et de scanner les codes de
> configuration correspondants.** Ces guides (Virtuagym et autres logiciels de
> contrôle d'accès) mettent le scanner en mode port série : il bipera, mais la
> caisse ne recevra **rien**. Si c'est déjà fait, voir §6 pour revenir en
> arrière.

En clair : **brancher le scanner, ne rien configurer.** Aucun pilote à
installer, le scanner est reconnu par Windows comme un clavier.

---

## 2. Brancher et vérifier en 2 minutes

1. Brancher le scanner sur un port USB du PC de caisse.
2. Attendre le bip de démarrage (Windows installe le périphérique tout seul).
3. Ouvrir le **Bloc-notes**, scanner n'importe quel code-barres : le code doit
   s'écrire dans le Bloc-notes.
4. Ouvrir la caisse (`/admin/pos`). En haut du panier, un voyant doit
   apparaître au bout de ~3 secondes :

   **🟢 Scanner USB connecté** → tout est bon, l'installation est terminée.

Si le code s'écrit dans le Bloc-notes, le scanner fonctionne. Le voyant, lui,
dépend de l'endroit où tourne l'application : voir §4.

---

## 3. Les 4 états du voyant

Le voyant se trouve en haut du panier, sur la page **Point de vente**. Il se
met à jour tout seul : débrancher le scanner l'éteint en ~10 secondes, il n'y
a pas besoin de recharger la page.

| Voyant | Signification | Que faire |
|:--|:--|:--|
| 🟢 **Scanner USB connecté** | Windows voit le scanner, il est prêt. | Rien. |
| 🔴 **Scanner USB non connecté** | Windows ne voit aucun scanner Honeywell. | Vérifier le câble USB, changer de port, rebrancher. |
| 🟠 **Scanner USB en mode série (COM)** | Le scanner est branché mais en mode port série : il bipe, la caisse ne reçoit rien. | Le remettre en mode clavier : voir §6. |
| 🔴 **Scanner USB : problème de pilote** | Branché, mais Windows signale une erreur. | Débrancher, rebrancher. Si ça persiste : autre port USB, autre câble. |

Survoler le voyant à la souris affiche la même explication.

Les textes existent en **français, arabe et darija** et suivent la langue de
l'interface.

---

## 4. Où le voyant fonctionne (important)

Le voyant décrit **le PC qui exécute PHP**, pas le PC de la caissière.

| Installation | Voyant |
|:--|:--|
| L'application tourne **sur le PC de caisse** (XAMPP / `php artisan serve`, Windows) | ✅ Fonctionne |
| L'application est sur un **serveur distant** (hébergement web) | ❌ Aucun voyant affiché |
| Le serveur est sous **Linux** | ❌ Aucun voyant affiché |
| La caisse est ouverte **depuis un autre PC** que celui qui héberge l'app | ⚠️ Le voyant décrit le PC serveur, pas celui de la caissière |

**Dans tous ces cas, le scanner continue de fonctionner normalement** : il tape
les codes dans la page, la vente se fait. Seul l'affichage de l'état disparaît.
C'est volontaire : un serveur qui ne peut pas répondre n'affiche rien plutôt que
d'annoncer à tort « scanner non connecté ».

> 💡 Pour que le client ait le voyant, installer l'application **sur le PC de
> caisse** (XAMPP sous Windows). C'est le cas d'usage prévu.

---

## 5. Scanner d'une autre marque

Le voyant cherche par défaut l'identifiant USB de Honeywell (`VID_0C2E`), qui
couvre le 7580g et ses variantes (7580g-2, Genesis XP, Voyager…).

Pour un scanner d'une autre marque, ajouter son identifiant dans le fichier
`.env` du projet :

```ini
SCANNER_USB_VID=VID_05E0
```

| Marque | Identifiant |
|:--|:--|
| Honeywell (défaut) | `VID_0C2E` |
| Zebra / Symbol | `VID_05E0` |
| Datalogic | `VID_05F9` |
| Newland | `VID_1EAB` |

Puis vider le cache de configuration :

```bash
php artisan config:clear
```

**Trouver l'identifiant d'un scanner inconnu** — brancher le scanner, ouvrir
PowerShell et lancer :

```powershell
Get-PnpDevice -PresentOnly -Class Keyboard,Ports,HIDClass |
  Where-Object { $_.InstanceId -like '*VID_*' } |
  Select-Object Status,Class,FriendlyName,InstanceId | Format-Table -AutoSize
```

Débrancher le scanner, relancer la commande, comparer : la ligne qui disparaît
est le scanner. Son `InstanceId` contient l'identifiant, par exemple
`USB\VID_0C2E&PID_0D01\...` → `VID_0C2E`.

Si aucun voyant n'apparaît alors que la valeur est correcte, laisser le champ
vide : une valeur invalide est ignorée et Honeywell est utilisé par défaut.

---

## 6. Remettre un scanner du mode série au mode clavier

Symptôme : le scanner bipe quand on scanne, mais **rien ne s'écrit** dans la
caisse ni dans le Bloc-notes. Le voyant affiche 🟠 **mode série (COM)**.

Cela arrive quand le scanner a été configuré pour un autre logiciel.

**Solution 1 (sans rien imprimer) :** lancer le script fourni, scanner branché,
dans PowerShell à la racine du projet :

```powershell
powershell -ExecutionPolicy Bypass -File .\deploy\scanner-mode-clavier.ps1
```

Il trouve le port COM du scanner, lui envoie la commande menu Honeywell
`PAP124.` (USB PC Keyboard, réglage permanent), attend le redémarrage du
scanner (bip) et vérifie que Windows voit maintenant un clavier. Vérifié sur
un 7580 réel le 26/09/2026. Ajouter `-WhatIf` pour seulement afficher l'état.

**Solution 2 :** scanner le code-barres **« USB Keyboard (PC) »** du manuel
Honeywell 7580g — chapitre *Interfaces* / *Terminal Interfaces*, au tout début
du manuel. Un seul code suffit, le scanner bipe et repasse en mode clavier.

Le manuel officiel est téléchargeable sur le site de Honeywell (rechercher
« Genesis 7580g User Guide »).

En dernier recours, le code **« Reset Factory Defaults »** (« Restaurer les
valeurs d'usine ») du même manuel remet le scanner en mode clavier, puisque
c'est son réglage d'origine.

> 💡 Astuce : si scanner le code depuis l'écran ne fonctionne pas (le scanner
> bipe sans effet), **imprimer la page** et scanner le code sur papier.

---

## 7. Dépannage

**Le code s'écrit dans le Bloc-notes mais pas dans la caisse.**
Cliquer dans la page de caisse pour lui donner le focus, puis scanner. La
caisse attrape les scans où que soit le curseur, mais la fenêtre du navigateur
doit être active.

**Le voyant n'apparaît jamais, même après 30 secondes.**
Vérifier que l'application tourne bien sur ce PC Windows (§4). Sinon, vérifier
que PowerShell répond :

```powershell
Get-PnpDevice -PresentOnly | Where-Object { $_.InstanceId -like '*VID_0C2E*' } |
  Select-Object Status,Class,FriendlyName | Format-Table -AutoSize
```

Scanner branché, cette commande doit lister des lignes. Si elle ne renvoie
rien alors que le scanner fonctionne dans le Bloc-notes, c'est probablement un
scanner d'une autre marque : voir §5.

**Le voyant reste rouge alors que le scanner marche.**
Même cause : l'identifiant USB ne correspond pas. Voir §5. Ce n'est pas
bloquant, les ventes se font normalement.

**Le voyant est lent à réagir.**
Normal : l'état est rafraîchi toutes les ~10 secondes, et le voyant n'apparaît
qu'environ 3 secondes après l'ouverture de la page.

**Faut-il installer un pilote ?**
Non. En mode clavier, Windows reconnaît le scanner sans rien installer.

---

## 8. Checklist de mise en service

- [ ] Scanner branché, bip de démarrage entendu
- [ ] Un code s'écrit correctement dans le Bloc-notes
- [ ] L'application tourne sur le PC de caisse (sinon : pas de voyant, §4)
- [ ] La page `/admin/pos` affiche 🟢 **Scanner USB connecté**
- [ ] Un scan ajoute bien l'article au panier
- [ ] Débrancher le scanner : le voyant passe au rouge en ~10 s
- [ ] Rebrancher : le voyant repasse au vert
- [ ] Montrer le voyant à la caissière : **vert = prêt, rouge = appeler**

---

## 9. Détails techniques

Pour la maintenance, pas pour l'installation.

L'état vient de Windows via PowerShell (`Get-PnpDevice -PresentOnly`), la même
liste que le Gestionnaire de périphériques. Le code est dans
[`app/Support/ScannerUsb.php`](../app/Support/ScannerUsb.php).

PowerShell met ~2 secondes à démarrer, et `php artisan serve` traite une
requête à la fois : interroger Windows pendant une requête figerait la caisse,
scans compris. La sonde est donc lancée **en arrière-plan** et écrit sa réponse
dans `storage/app/scanner-usb.json` ; les pages ne font que lire ce fichier.
Le point de vente déclenche le rafraîchissement sur le sondage qu'il fait déjà
pour le scanner-téléphone, toutes les 0,9 s, avec un intervalle minimum de
10 secondes entre deux interrogations de Windows.

Une réponse de plus de 60 secondes est considérée périmée et affichée comme
« inconnue » (aucun voyant), plutôt que comme « non connecté ».

Le fichier `storage/app/scanner-usb.json` est régénéré tout seul : il peut être
supprimé sans risque.

Tests : `php artisan test --filter=ScannerUsbTest`.

> ✅ **Vérifié le 26/09/2026 avec un Genesis 7580 réel** (firmware 5646,
> Windows 11). Le scanner est arrivé en mode série : Windows l'affichait comme
> « Périphérique série USB (COM9) » (`VID_0C2E&PID_0BEA`, interface CDC-ACM) et
> la sonde répondait `serie: true`. Après `PAP124.` il se présente comme
> `VID_0C2E&PID_0BE1` avec une interface « HID Keyboard Emulation » (classe
> Keyboard) et une interface « HID POS » (classe BarcodeScanner), plus aucun
> port COM, et la sonde répond `connected`. L'état « problème de pilote » reste
> couvert par les tests automatisés uniquement.
>
> Le PID change avec le mode (0BEA en série, 0BE1 en clavier) : c'est pour cela
> que la sonde filtre sur le VID seul.
