# Install

Install packer

```
apt install packer
```

# Use

Source .openrc

```
source ../.openrc
```

# Build images

**Valheim**

```
packer build -var-file valheim-var.json packer.json
```
