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
