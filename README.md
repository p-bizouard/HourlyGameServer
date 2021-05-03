### Hourly Game Server

HourlyGameServer is a webapp than can:
- start and stop Valheim server on Openstack instance
- backup and restore the world from an Openstack Object Storage
- share the manager access to another users.

It uses Kubernetes, Ansible, Terraform, Packer, PHP8 and Symfony5

### Build docker image

Build image

```
docker build -t hgs_php -f ./docker/php/Dockerfile --target dev .
```

### Build packer images

```
cd packer
```

[Packer Readme](packer/README.md)

### Launch terraform

```
cd terraform
```

[Terraform Readme](terraform/README.md)

### Launch playbooks

```
cd ansible
```

[Ansible Readme](ansible/README.md)
