terraform {
  required_providers {
    openstack = {
      source = "terraform-provider-openstack/openstack"
      version = "~> 1.28"
    }
    local = {
      source = "hashicorp/local"
      version = "~> 1.4.0"
    }
  }
  backend "swift" {
    container         = "terraform-state"
    archive_container = "terraform-state-archive"
    region_name       = "GRA"
  }
}

provider "openstack" {
  region = "GRA5"
  auth_url = "https://auth.cloud.ovh.net/v3"
}

resource "openstack_compute_instance_v2" "main_instance" {
  name        = var.instance_name
  region      = "GRA5"
  image_name  = var.instance_image
  flavor_name = var.instance_type
  key_pair    = var.key_pair
}

resource "local_file" "gitlab_ansible_inventory" {
  filename = "../inventories/${var.game}/hosts"
  content = templatefile("./ansible-inventory.tpl", {
    server = openstack_compute_instance_v2.main_instance,
    game = var.game
  })
}
