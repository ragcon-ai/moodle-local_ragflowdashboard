<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * German language strings for local_ragflowdashboard.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['anonymize'] = 'Logdaten anonymisieren';
$string['anonymize_desc'] = 'Keinen Nutzerbezug je Logeintrag speichern (Nutzer-ID 0). Aggregierte Statistiken funktionieren weiter, eine nutzerbezogene Auswertung und der Datenschutz-Export einzelner Nutzung jedoch nicht.';
$string['apicall_apply'] = 'Anwenden';
$string['apicall_from'] = 'Von';
$string['apicall_live'] = 'Live-Ansicht';
$string['apicall_live_help'] = 'Die Liste alle paar Sekunden automatisch neu laden.';
$string['apicall_next'] = 'Weiter';
$string['apicall_page'] = 'Seite {$a->page} von {$a->pages}';
$string['apicall_perpage'] = 'Pro Seite';
$string['apicall_prev'] = 'Zurück';
$string['apicall_search'] = 'Suchtext';
$string['apicall_status'] = 'HTTP-Status';
$string['apicall_to'] = 'Bis';
$string['apicall_total'] = '{$a} passende Aufrufe';
$string['apilog'] = 'RAGflow-API-Aufrufe (roh)';
$string['apilog_cause'] = 'Ursache';
$string['apilog_none'] = 'Noch keine API-Aufrufe erfasst.';
$string['apilog_nostatus'] = 'keine Antwort';
$string['apilog_off'] = 'Das rohe API-Logging ist aus. Aktivieren Sie es in den Einstellungen unten, um Aufrufe hier zu erfassen.';
$string['apilog_on'] = 'Das rohe API-Logging ist AN – jeder RAGflow-Aufruf wird unten aufgezeichnet. Nach Abschluss wieder ausschalten.';
$string['apilog_request'] = 'Anfrage';
$string['apilog_response'] = 'Antwort';
$string['apilog_url'] = 'URL';
$string['chart:bycomponent'] = 'Anfragen je Funktion';
$string['chart:bycourse'] = 'Top 10 Kurse';
$string['chart:byerrortype'] = 'Fehler nach Typ';
$string['chart:byrole'] = 'Anfragen je Nutzergruppe';
$string['chart:byuser'] = 'Top 10 Nutzer';
$string['chart:tokensbyinstance'] = 'Tokens je Instanz';
$string['chart:tokensbyplugin'] = 'Tokens je Plugin';
$string['chart:tokensperday'] = 'Tokens pro Tag';
$string['chart:usage'] = 'Anfragen pro Tag';
$string['col:action'] = 'Aktion';
$string['col:component'] = 'Funktion';
$string['col:errortype'] = 'Fehlertyp';
$string['col:latency'] = 'Latenz (ms)';
$string['col:question'] = 'Frage';
$string['col:response'] = 'Antwort';
$string['col:time'] = 'Zeit';
$string['course_none'] = 'Außerhalb eines Kurses';
$string['debugapiraw'] = 'Rohes RAGflow-API-Aufrufprotokoll';
$string['debugapiraw_desc'] = 'Protokolliert jeden RAGflow-API-Aufruf (URL, JSON-Anfrage und rohe Antwort) in eine nur für Administratoren sichtbare Tabelle auf diesem Dashboard, mit einer aufklappbaren Zeile je Aufruf. Der API-Schlüssel wird nie protokolliert. Nur zur Fehlersuche aktivieren – es werden Anfrage- und Antwortinhalte gespeichert; danach wieder ausschalten.';
$string['debugcaptures'] = 'Debug-Aufzeichnungen';
$string['debugfor'] = 'Debug: {$a}';
$string['debugheading'] = 'Debug-Modus je Funktion';
$string['debugheading_desc'] = 'Wenn für eine Funktion aktiviert, werden die (begrenzten) Anfrage- und Antwortinhalte zur Fehlersuche gespeichert. Dabei werden Nutzernachrichten und Antworten erfasst – nur vorübergehend aktivieren und den Datenschutz beachten.';
$string['detailmaxlen'] = 'Debug-Inhaltslimit (Zeichen)';
$string['detailmaxlen_desc'] = 'Maximale Anzahl gespeicherter Zeichen je erfasster Frage und Antwort.';
$string['errorlog'] = 'Letzte Fehler';
$string['errors'] = 'Fehler';
$string['errortype:embedding'] = 'Embedding-Fehler';
$string['errortype:embedding_contextwindow'] = 'Anfrage zu lang für Embedding-Modell';
$string['errortype:http_4xx'] = 'RAGflow-Client-Fehler (4xx)';
$string['errortype:http_5xx'] = 'RAGflow-Server-Fehler (5xx)';
$string['errortype:network'] = 'Netzwerk / Timeout';
$string['errortype:notconfigured'] = 'Nicht konfiguriert';
$string['errortype:ragflow'] = 'RAGflow-Fehler';
$string['errortype:ratelimited'] = 'Ratenlimit erreicht';
$string['errortype:session'] = 'Sitzungsfehler';
$string['errortype:unexpected'] = 'Unerwartete Antwort';
$string['errortype:unknown'] = 'Unbekannt';
$string['export'] = 'Export (alle Ansichten)';
$string['export:allviews'] = 'Alle Ansichten';
$string['export:col:action'] = 'Aktion';
$string['export:col:component'] = 'Komponente';
$string['export:col:course'] = 'Kurs';
$string['export:col:errortype'] = 'Fehlertyp';
$string['export:col:itemcount'] = 'Elemente';
$string['export:col:latencyms'] = 'Latenz (ms)';
$string['export:col:success'] = 'Erfolg';
$string['export:col:time'] = 'Zeit';
$string['export:col:tokenscompletion'] = 'Completion-Tokens';
$string['export:col:tokensprompt'] = 'Prompt-Tokens';
$string['export:col:tokenstotal'] = 'Tokens gesamt';
$string['export:col:user'] = 'Nutzer';
$string['export:col:view'] = 'Ansicht';
$string['export:kpi:failures'] = 'Fehler';
$string['export:kpi:requests'] = 'Anfragen';
$string['export:kpi:successrate'] = 'Erfolgsquote';
$string['export:kpi:tokens'] = 'Tokens gesamt';
$string['export:none'] = '—';
$string['export:norows'] = 'Keine Daten in diesem Zeitraum.';
$string['export:otherview'] = 'Sonstige';
$string['export:pdftitle'] = 'RAGflow-Nutzungsbericht';
$string['export:pdftruncated'] = 'Es werden nur die ersten {$a} Zeilen gezeigt — für die vollständigen Daten CSV oder XML nutzen.';
$string['exportbutton'] = 'Exportieren';
$string['exportformat'] = 'Format';
$string['exportformat:csv'] = 'CSV';
$string['exportformat:pdf'] = 'PDF';
$string['exportformat:xml'] = 'XML';
$string['exportfrom'] = 'Von';
$string['exportto'] = 'Bis';
$string['failed'] = 'Fehlgeschlagen';
$string['kpi:avglatency'] = 'Durchschnittliche Latenz';
$string['kpi:failures'] = 'Fehler';
$string['kpi:requests'] = 'Anfragen (letzte {$a} Tage)';
$string['kpi:successrate'] = 'Erfolgsquote';
$string['kpi:tokenscompletion'] = 'Antwort-Tokens';
$string['kpi:tokensprompt'] = 'Prompt-Tokens';
$string['kpi:tokenstotal'] = 'Tokens gesamt';
$string['nodata'] = 'Noch keine Daten.';
$string['noerrors'] = 'Keine Fehler in diesem Zeitraum.';
$string['pluginname'] = 'RAGflow Dashboard';
$string['privacy:metadata:apilog'] = 'Optionales rohes RAGflow-API-Aufrufprotokoll (Anfrage- und Antwortinhalte), nur gespeichert, solange der Schalter für das rohe API-Logging aktiv ist.';
$string['privacy:metadata:apilog:method'] = 'Die HTTP-Methode des Aufrufs.';
$string['privacy:metadata:apilog:request'] = 'Die an RAGflow gesendete JSON-Anfrage (begrenzt).';
$string['privacy:metadata:apilog:response'] = 'Der rohe Antwortinhalt von RAGflow (begrenzt).';
$string['privacy:metadata:apilog:status'] = 'Der von RAGflow zurückgegebene HTTP-Statuscode.';
$string['privacy:metadata:apilog:timecreated'] = 'Wann der Aufruf erfolgte.';
$string['privacy:metadata:apilog:url'] = 'Die aufgerufene RAGflow-API-Endpunkt-URL (ohne Zugangsdaten).';
$string['privacy:metadata:apilog:userid'] = 'Der Nutzer, in dessen Auftrag der Aufruf erfolgte (sofern die Anonymisierung nicht aktiv ist).';
$string['privacy:metadata:debug'] = 'Optionale funktionsbezogene Debug-Aufzeichnungen von Anfrage- und Antwortinhalten, nur gespeichert, solange der Debug-Modus einer Funktion aktiv ist.';
$string['privacy:metadata:debug:action'] = 'Die Art der Anfrage (Chat oder Suche).';
$string['privacy:metadata:debug:component'] = 'Die Funktion, aus der die Anfrage kam.';
$string['privacy:metadata:debug:question'] = 'Die Nutzerfrage oder -suche (begrenzt).';
$string['privacy:metadata:debug:response'] = 'Die Antwort, der Fehler oder die Ergebnisübersicht (begrenzt).';
$string['privacy:metadata:debug:timecreated'] = 'Wann die Anfrage erfolgte.';
$string['privacy:metadata:debug:userid'] = 'Der anfragende Nutzer (sofern die Anonymisierung nicht aktiv ist).';
$string['privacy:metadata:log'] = 'Ein Protokoll der RAGflow-Provider-Nutzung (Chat- und Suchanfragen) für Statistik und Fehlersuche.';
$string['privacy:metadata:log:action'] = 'Die Art der Anfrage (Chat oder Suche).';
$string['privacy:metadata:log:component'] = 'Die Funktion, aus der die Anfrage kam.';
$string['privacy:metadata:log:courseid'] = 'Der Kurs, in dem die Anfrage erfolgte (falls vorhanden).';
$string['privacy:metadata:log:errortype'] = 'Der Fehlertyp, falls die Anfrage fehlschlug.';
$string['privacy:metadata:log:success'] = 'Ob die Anfrage erfolgreich war.';
$string['privacy:metadata:log:timecreated'] = 'Wann die Anfrage erfolgte.';
$string['privacy:metadata:log:userid'] = 'Der anfragende Nutzer (sofern die Anonymisierung nicht aktiv ist).';
$string['ragflowdashboard:view'] = 'Das RAGflow-Nutzungs-Dashboard und die Logs einsehen';
$string['reload'] = 'Daten neu laden';
$string['requests'] = 'Anfragen';
$string['retentiondays'] = 'Log-Aufbewahrung (Tage)';
$string['retentiondays_desc'] = 'Logeinträge löschen, die älter als so viele Tage sind. 0 = unbegrenzt aufbewahren.';
$string['role_anon'] = 'Anonymisiert';
$string['role_student'] = 'Studierende / Nutzer';
$string['role_trainer'] = 'Trainer';
$string['settings'] = 'RAGflow-Dashboard-Einstellungen';
$string['status_action'] = 'Aktion: {$a}';
$string['status_actions_heading'] = 'Provider-Aktionen';
$string['status_apicall'] = 'Prüfergebnis (API-Call)';
$string['status_assistant_nokb'] = 'Assistant ist mit keiner Wissensbasis verknüpft';
$string['status_assistant_none'] = 'Kein Assistant ausgewählt';
$string['status_assistant_notfound'] = 'Assistant in RAGflow nicht gefunden';
$string['status_assistant_ok'] = 'Assistant „{$a}" OK';
$string['status_call_configonly'] = 'Lokale Konfigurationsprüfung (kein API-Call).';
$string['status_connection'] = 'RAGflow-Verbindung';
$string['status_connection_fail'] = 'Nicht erreichbar — Base-URL, API-Key und RAGflow-Dienst prüfen';
$string['status_connection_ok'] = 'Erreichbar — {$a->datasets} Wissensbasis(en), {$a->assistants} Assistant(s)';
$string['status_context_gone'] = 'Unbekannter Ort';
$string['status_context_site'] = 'Startseite';
$string['status_docs'] = '{$a} geparste(s) Dokument(e)';
$string['status_filter_placeholder'] = 'Nach Kurs oder Instanz filtern…';
$string['status_instance'] = 'Instanz #{$a}';
$string['status_kb_count'] = '{$a} Wissensbasen';
$string['status_kb_empty'] = 'Wissensbasis „{$a}" hat noch keinen geparsten Inhalt';
$string['status_kb_nodocs'] = 'Wissensbasis „{$a}" enthält noch keine Dokumente';
$string['status_kb_none'] = 'Keine Wissensbasis ausgewählt';
$string['status_kb_notfound'] = 'Wissensbasis in RAGflow nicht gefunden';
$string['status_kb_ok'] = '„{$a->name}": {$a->docs} geparste(s) Dokument(e)';
$string['status_lastchecked'] = 'Geprüft um {$a}';
$string['status_link_chat'] = 'RAGflow Chat-App';
$string['status_link_course'] = 'Moodle-Kurs: {$a}';
$string['status_link_kb'] = 'RAGflow Wissensdatenbank';
$string['status_link_newwindow'] = '(öffnet in neuem Fenster)';
$string['status_link_settings'] = 'Einstellungen';
$string['status_noinstances'] = 'Keine konfigurierten Instanzen';
$string['status_placement'] = 'Platzierung (systemweit)';
$string['status_plugin_dashboard'] = 'RAGflow-Dashboard (dieses Plugin)';
$string['status_plugin_helpdesk'] = 'Helpdesk-Platzierung';
$string['status_plugin_installed'] = 'Installiert';
$string['status_plugin_missing'] = 'Nicht installiert';
$string['status_plugin_provider'] = 'RAGflow-Provider';
$string['status_plugin_search'] = 'Such-Block';
$string['status_plugin_tutor'] = 'Tutor-Block';
$string['status_plugins_heading'] = 'Suite-Plugins';
$string['status_provider'] = 'RAGflow-Provider-Instanz';
$string['status_provider_heading'] = 'Provider & Verbindung';
$string['status_provider_missing'] = 'Nicht konfiguriert — Base-URL und API-Key an der Provider-Instanz setzen';
$string['status_provider_ok'] = 'Konfiguriert (Base-URL + API-Key gesetzt)';
$string['status_refresh'] = 'Aktualisieren';
$string['status_section_instances'] = 'Plugin-Instanzen';
$string['status_section_system'] = 'Systemkonfiguration';
$string['status_state_error'] = 'Fehler';
$string['status_state_info'] = 'Nicht konfiguriert';
$string['status_state_ok'] = 'OK';
$string['status_state_warn'] = 'Warnung';
$string['subplugintype_rfdsource'] = 'RAGflow-Dashboard-Quelle';
$string['subplugintype_rfdsource_plural'] = 'RAGflow-Dashboard-Quellen';
$string['successful'] = 'Erfolgreich';
$string['tab_apicalls'] = 'API-Aufrufe';
$string['tab_errors'] = 'Fehler';
$string['tab_export'] = 'Export';
$string['tab_status'] = 'Status';
$string['tab_tokens'] = 'Tokens';
$string['tab_usage'] = 'Nutzung';
$string['task:purgelogs'] = 'Alte RAGflow-Nutzungslogs bereinigen';
$string['tokeninstanceunknown'] = 'Unbekannte Instanz';
$string['tokensinfo'] = 'Tokens werden nur für den Chat gezählt (die Suche verbraucht keine) und nur für Chats über den OpenAI-kompatiblen RAGflow-Endpoint. Chats mit Session-Memory nutzen den nativen RAGflow-Endpoint, der keine Token-Daten liefert und daher nicht gezählt wird. Die Zählung beginnt mit der Installation (keine Historie) und gibt die von RAGflow gemeldeten Werte wieder – ohne Gewähr auf Vollständigkeit oder Genauigkeit.';
$string['view'] = 'Ansicht';
$string['viewall'] = 'Alle Funktionen';
$string['windowdays'] = 'Zeitraum (Tage)';
$string['windowtoday'] = 'Heute';
