# Koyomi: technische Grundlage für den Datenschutztext

(English version: [PRIVACY.md](PRIVACY.md). Die Optionen heißen `koyomi.*`; die
Namen stehen im [README](README.md#options).)

Dieser Baustein beschreibt die Implementierung des Moduls. Rechtsgrundlage,
Interessenabwägung und die endgültige Formulierung müssen für den konkreten
Betrieb fachlich geprüft und ergänzt werden. Der Text ist keine Rechtsberatung.

## Veranstaltungskalender — technischer Textbaustein

Auf unserer Website veröffentlichen wir Veranstaltungen mit Termin, Ort, Beschreibung
und gegebenenfalls Veranstaltenden. Die Seiten der Veranstaltungen bauen Ihr Browser
**ohne Verbindung zu Drittanbietern** auf: Es wird kein Skript, keine Einbettung,
keine Schrift und kein Bild von fremden Servern nachgeladen. Eine Karte zeigen wir
als Bild von unserem eigenen Server. Ein Kartenlink öffnet OpenStreetMap erst dann,
wenn Sie ihn anklicken.

Die Veranstaltungsseiten setzen keine Cookies, verwenden keinen Speicher Ihres
Browsers und erheben keine Daten über Besucherinnen und Besucher.

Folgende Links verlassen unsere Website nur auf Ihren Klick:

- **„Zu Google Kalender hinzufügen“**: Der Link öffnet Google Kalender mit einem
  vorausgefüllten Termin. Dabei übermittelt Ihr Browser an Google den Titel der
  Veranstaltung, Datum und Uhrzeit, die Zeitzone, den Ort, eine gekürzte Beschreibung
  und die Adresse der Veranstaltungsseite. Dort gilt die Datenschutzerklärung von Google.
- **Anmelde- und Buchungslinks**: Sie führen zu dem Anbieter, den wir für die jeweilige
  Veranstaltung angegeben haben, und öffnen sich in einem neuen Tab. Dort gelten dessen
  Datenschutzbestimmungen.
- **Kartenlink**: öffnet OpenStreetMap. Dort gilt die Datenschutzerklärung der
  OpenStreetMap-Stiftung.

Veranstaltungen können Sie als Kalenderdatei (`.ics`) herunterladen oder als
Kalender abonnieren. Außerdem gibt es einen RSS-Feed und, sofern aktiviert, einen
JSON-Feed. Diese Adressen sind öffentlich und können von jedem abgerufen werden.
Kalender-Apps, die ein Abonnement pflegen, rufen sie in eigenen Abständen erneut ab.
Dabei werden die Daten veröffentlicht, die auch auf den Seiten stehen: Titel,
Termin, Ort und Beschreibung, Namen der Veranstaltenden und deren öffentliche
Angaben.

Der Zugangslink zu Online-Veranstaltungen wird **nie** veröffentlicht. Die E-Mail-Adresse
einer veranstaltenden Person erscheint nur dann, wenn diese Person der Veröffentlichung
ausdrücklich zugestimmt hat und die Angabe im Panel freigeschaltet ist.

## Hinweise für den Betreiber

- **Was öffentlich ist.** Seiten, Kalenderdateien (`.ics`), RSS-Feed, JSON-Feed (nur
  wenn eingeschaltet) und JSON-LD in den Veranstaltungsseiten enthalten dieselben
  Angaben: Titel, Termin, Status, Ort, Beschreibung, Veranstaltende und deren
  Webadressen, Preis und Buchungslink. Veranstaltungsorte mit Adresse, Koordinaten
  und Anfahrt sind öffentlich, weil dort die Veranstaltung stattfindet. Die JSON-LD
  enthält zusätzlich das Titelbild und, nur bei eingeschaltetem `showEmail`, die
  öffentliche E-Mail-Adresse der veranstaltenden Person.
- **Entwürfe.** Eine Veranstaltung im Status Entwurf erscheint nirgends. Ein Entwurf,
  auf den eine andere Veranstaltung verweist (Teil einer Reihe, Veranstaltungsort,
  veranstaltende Person), wird in deren Ausgaben ebenfalls nicht gezeigt, bis er
  veröffentlicht ist.
- **Was nie öffentlich ist.** Der Zugangslink zu Online-Veranstaltungen (`onlineUrl`)
  steht nur im Panel und in der Inhaltsdatei. Er erscheint in keiner Ausgabe. Die
  E-Mail-Adresse einer veranstaltenden Person (`email`) wird nur gelesen und ausgegeben,
  wenn `showEmail` eingeschaltet ist.
- **Zustimmung der Veranstaltenden.** Namen, Webadressen und Fotos der Veranstaltenden
  veröffentlichen Sie mit deren Einverständnis. Die E-Mail-Adresse nur mit einer
  ausdrücklichen Zustimmung; der Schalter `showEmail` dokumentiert sie im Panel.
- **Feeds sind öffentlich.** Kalenderdateien und Feeds sind öffentliche Adressen ohne
  Zugriffsschutz, auch wenn eine Veranstaltung im Menü nicht auftaucht. Nichts in Titel
  oder Beschreibung einer Veranstaltung schreiben, was nicht öffentlich sein darf.
- **Speicherung.** Die Daten liegen in den Inhaltsdateien der Website, im Repository
  und auf dem Webserver. Das Modul selbst legt keine Datenbank an und protokolliert
  keine Besuche. Ist der Seitencache von Kirby eingeschaltet, speichert er gerenderte
  Seiten wie bei jeder Kirby-Website. Fehler über ungültige Termine landen im
  PHP-Fehlerprotokoll und nennen nur die Kennung der Veranstaltung.
- **Git-Geschichte.** Frühere Stände der Inhaltsdateien bleiben im Repository erhalten.
  Wer entfernte Angaben auch dort löschen will, muss das in Git selbst tun.
- **Google-Kalender-Link.** Er ist ein gewöhnlicher Link und überträgt Daten erst beim
  Klick. Ob Sie ihn anbieten, steuern Sie mit der Option `koyomi.google`.
- **Kartenbilder** lädt das Modul nicht aus Drittquellen. Laden Sie ein statisches Bild
  selbst hoch, oder lassen Sie den Kartenblock weg (`showMap` ausgeschaltet).
- **Einbindung in die Datenschutzerklärung.** Der Textbaustein oben kann so übernommen
  werden, muss aber zu den tatsächlich genutzten Ausgaben und Links passen. Schalten
  Sie Ausgaben, die Sie nicht anbieten, mit den Optionen `ics`, `feed`, `rss` und `json`
  ab, und streichen Sie den entsprechenden Absatz.
