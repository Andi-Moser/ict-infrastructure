# ICT-BZ Infrastruktur

Dieses Repository enthält die Dokumentationen sowie nötigen Konfigurationsfiles für einen Teil der Server Infrastruktur der ICT-BZ.

## Allgemeine Struktur

Jeder der hier beschriebenen Server (auch Service genannt) enthält als ein `docker-compose.yml` File als Einstiegspunkt. Auf jedem Server werden die Volumnes der Docker Container jeweils im `/data` Ordner abgelegt.

> [!CAUTION]
> Das `/data` Verzeichnis darf auf keinen Fall gelöscht werden. Werden Anpassungen z.B. an der Software Version gemacht sollte immer zuerst ein Backup davon erstellt werden.

Wo ein `.env.example` File vorhanden ist muss dieses zuerst zu einem `.env` File kopiert werden und anschliessend müssen die Werte gesetzt werden.

## Services

### NGINX

| Servername `edu10` / IP `10.200.2.104`

Der Nginx Server dient als Reverse-Proxy für alle Services welche über das öffentliche Internet aufrufbar sind.

#### Konfiguration

Die Konfiguration des Reverse-Proxy ist in der Datei `/nginx/nginx/conf/nginx.conf` abgelegt und dokumentiert. Neue Services können dort entsprechend den Vorlagen registriert werden.

#### Zertifikate generieren

Um ein neues Zertifikat für einen Service zu generieren kann das `generate-cert.sh` Script wie folgt verwendet werden:

```sh
./generate-cert.sh servicename.ict-bz.ch
```

> Wenn ein neuer Service eingerichtet wird muss der SSL Server im `nginx.conf` zuerst auskommentiert werden bis das Zertifikat generiert wird (der Webserver startet nicht ohne Zertifikat und das Zertifikat kann ohne laufenden Webserver nicht generiert werden.)

#### Zertifikate erneuern

Die Zertifikate müssen jeweils mit `renew-certs.sh` erneuert werden. Dies sollte per Cronjob (`crontab -e`) registriert werden:

```
0 4 20 * * /home/admin-ict/ict-infra/nginx/renew-certs.sh # An jedem 20. des Monats um 04:00 Uhr
```

### Moodle

| Servername `edu11` / IP `10.200.2.111`

#### Installation

Damit der MariaDB Server seine Datenverzeichnisse korrekt anlegen kann muss zuerst das Script `/moodle/setup.sh` als `root` ausgeführt werden.

#### Standard Zugänge

Der Standard-Admin Zugang lautet `user` / `bitnami`.

### OwnCloud

| Servername `edu12` / IP `10.200.2.112`

#### Installation

Der OwnCloud Service benötigt keine besonderen Vorarbeiten zur Installation.

### LDAP

| Servername `edu13` / IP `10.200.2.113`

#### Installation

| Der LDAP Server benötigt zusätzliche Pakete auf dem Host-System um die LDAP Grundstruktur sowie User anzulegen: `sudo apt install ldap-utils slapd`

Bevor der LDAP Container gestartet werden kann müssen die Datenverzeichne über das Script `/ldap/setup.sh` als `root` angelegt werden.

Sobald der Server läuft muss noch das Script `setup_ou.sh` ausgeführt werden um die Instruktoren/Lernenden Gruppen im LDAP anzulegen.

#### Datenimport

Mit dem Script `/ldap/batch_create_users.sh` kann eine CSV Datei eingelesen werden und die User darin erstellt werden.