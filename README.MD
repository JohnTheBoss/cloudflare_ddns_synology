# Cloudflare DDNS Updater Script on Synology DSM

This script support **one domain / subdomain** and IPv4, IPv6.

The script automatically create DNS record in zone if not exist.  

## Before Install
1. Login Cloudflare
2. Create or Use API Token: https://dash.cloudflare.com/profile/api-tokens with following permissions
    - **Zone > DNS > Edit**
    - **Zone > Zone > Read**
    - **Zone > Zone Settings > Read**

## Install

1. Activate SSH access on your DSM 

   Navigate to Control Panel > Terminal & SNMP > Enable SSH service

2. Connect via SSH

3. Run this script:
```shell
 sudo wget https://raw.githubusercontent.com/JohnTheBoss/cloudflare_ddns_synology/master/cloudflare.php -O /usr/syno/bin/ddns/cloudflare.php && sudo chmod 755 /usr/syno/bin/ddns/cloudflare.php
```
4. Add to DDNS Provider list:
```shell
sudo cp /etc.defaults/ddns_provider.conf /etc.defaults/ddns_provider.conf.orign ## Backup the original version
```
```shell
sudo tee -a /etc.defaults/ddns_provider.conf > /dev/null << END
[Cloudflare]
    modulepath=/usr/syno/bin/ddns/cloudflare.php
    queryurl=https://www.cloudflare.com/
END
```

5. Setup your DDNS settings:
   - Navigate to Control Panel > External Access > DDNS
   - Add new DDNS
      - Service provider: Cloudflare
      - Hostname: yourDomain.ltd
      - Username: Cloudflare email address
      - Password: Your created API Token
   - Save


6. **DISABLE SSH Access** (Navigate to Control Panel > Terminal & SNMP > Enable SSH service)