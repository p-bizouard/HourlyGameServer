# Install

Install packer

```
apt install packer
```

# Use

Source Openstack configuration and python environment

```
source ../.openrc
source ../ansible/env/bin/activate
```

# Build images

**Valheim**

```
packer build -var-file valheim-var.json packer.json
```
