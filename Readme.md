# SASEUL v2.1.9.6

- Copyright, 2019-2023, All rights reserved by ArtiFriends Inc.
- Commercial use of the source codes is strictly prohibited.
- Unauthorized use or any groundless allegation of the source codes can be subject to legal responsibility.
- The software is still experimental. The source code is continuously being modified so there is no guarantee that it wont't have to be restarted if necessary.

## Dependency

- The following PHP extension is required.
  - Common
    - bcmath
    - mbstring
    - sodium
  - Linux
    - process (posix)
    - pcntl
  - Windows
    - com_dotnet
  - (Optional) Attached DB
    - mysqlnd

## Usage

````
$ cp saseul.ini-sample saseul.ini
$ php src/saseul-script setenv
$ (... setting) 
$
$ usermod -a -G www saseul
$ usermod -a -G www nginx
$ usermod -a -G www apache
$ chown -Rf saseul:www data/
$ 
$ vi saseul.ini
$ (... modify genesis address)
$ 
$ php src/saseul-script start
$ php src/saseul-script log
$ php src/saseul-script genesis
$ php src/saseul-script genesisresource
````

## v2.2.x Preview

- Partial Ledger
  - Chain slot
  - Collecting status from peers
  - Dropping part of the data.