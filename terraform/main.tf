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
  region = var.instance_region
  auth_url = "https://auth.cloud.ovh.net/v3"
}

resource "openstack_compute_instance_v2" "main_instance" {
  name        = var.instance_name
  region      = var.instance_region
  image_name  = var.instance_image
  flavor_name = var.instance_type
}

output "instance_public_ip" {
  description = "Public IP address"
  value       = openstack_compute_instance_v2.main_instance.access_ip_v4
}