graph TD
    
    subgraph Stop
        Started --> |ansible vhserver pause| PauseServer[Pause game server]
        PauseServer --> |ansible restic backup| BackupServer[Backup game world]
        BackupServer --> |terraform destroy| StopServer[Stop server]
    end

    subgraph Start
        start[Start] --> |terraform init| InitTerraform[Init Terraform]
        InitTerraform --> |terraform apply openstack-image| BootServer[Boot server]
        BootServer --> |terraform result| GetIP[Get IP]
        GetIP --> |ansible restic restore| RestoreServer[Restore game world]
        RestoreServer --> |ansible vhserver update| UpdateServer[Update game server]
        UpdateServer --> |ansible vhserver start| StartServer[Start game server]
    end
    
    subgraph Packer
        StartPacker[Start packer instance] --> |packer build| Ubuntu[Ubuntu 20.04]
        Ubuntu --> |ansible install| Ansible[Ansible install latest vhserver]
        Ansible --> |openstack snapshot| OSImage[Openstack image]
    end
