# Install

[Install terraform](https://learn.hashicorp.com/tutorials/terraform/install-cli)

Source .openrc

```
source ../.openrc
```

Configure variables
```
cp terraform.tfvars.dist terraform.tfvars
```

Install providers 

```
terraform init
```

# Deploy


1. Run `terraform show` to see the current infra
1. Run `terraform plan` to see what changes need to be made
1. Run `terraform apply` to create the workers

# Destroy

```
terraform destroy
```