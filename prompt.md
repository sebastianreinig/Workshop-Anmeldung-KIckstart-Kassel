Ausgeführt in Claude.ai Sonnet 4.5:

# Ziel
Es geht um die Koordination von Workshops.
Für ein Event wird ein Workshop-Organisations-Tool benötigt.
Dieses Tool soll ermöglichen, dass Teilnehmer sich für Workshops anmelden können und der Organisator weiß wer sich wo angemeldet hat.

Ziel ist es ein Datenschutzkonforme Anwendung zu entwickeln.

# Rolle
Agiere als erfahrener Webanwendungsentwickler. 

# Rahmenbedingungen
Nutze HTML, CSS, JS, PHP und als Datenbank Maria DB/Mysql.


# Anwendung 1: Sicht Teilnehmer/Gast
- Der Teilnehmer kann Workshops sehen
- Der Teilnehmer gibt seinen Namen an
- Der Teilnehmer hat eine eindeutige ID (lokal speichern)
- Die Anwendung soll mobil nutzbar sein und darf fancy aussehen
- Titel der Anwendung: KIckstart Kassel
- Der Teilnehmer kann sich für maximal 2 Workshops anmelden
- Er erhält eine Rückmeldung für welche Workshops er sich angemeldet hat


# Schnittstelle Anwendung 1 und 2
- Gerne per REST-API.
- z.b. bekommt der User beim Aufruf eine eindeutige ID
- Die Liste der Workshops kommt per API
- Die Anmeldung zum Workshop erfolgt per API
- Die Rückmeldung erfolgt auch per API

# Anwendung 2: Organisator
- Der Organisator kann Workshops erstellen.
- Der Organisator sieht wer sich wo angemeldet hat
- Der Organisator kann Teilnehmer in andere Workshops verschieben
- Der Organisator kann einen Button klicken, der dann die "Organisation beendet" und den Teilnehmern mitteilt, in welchem Workshop diese sind.

# Setup
Liefere mir eine einfache setup-Datei (setup.php) in der ich Datenbankkonfiguration usw. vornehmen kann. Das Setup.php ist nur einmalig möglich! Sichere das z.b. über die Datenbank.
