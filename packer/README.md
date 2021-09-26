# Install

Install packer

```
apt install packer
```

# Use

Source Openstack and python environement

```
source ../.openrc
source env/bin/activate
```

# Build images

**Valheim**

```
packer build -var-file valheim-var.json packer.json
```
