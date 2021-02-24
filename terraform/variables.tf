variable "instance_image" {
  type = string
}
variable "instance_type" {
  type = string
}
variable "instance_name" {
  type = string
}
variable "instance_region" {
  type = string
  default = "GRA5"
}
variable "key_pair" {
  type = string
}
variable "game" {
  type = string
}
variable "state_name" {
  type = string
  default = "tfstate.tf"
}

