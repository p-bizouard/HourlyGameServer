#!/bin/bash

docker login harbor.bizouard.fr
docker build -t harbor.bizouard.fr/hgs/php:$(git log -1 --pretty=%h) -f docker/php/Dockerfile .
docker push harbor.bizouard.fr/hgs/php:$(git log -1 --pretty=%h)

helm upgrade -i --namespace hgs hgs ./helm/ \
--set image.repository=harbor.bizouard.fr/hgs \
--set image.tag="$(git log -1 --pretty=%h)" \
--set imagePullSecret="harbor-hgs" \
--set-file envFile=front/.env.k8s \
--set-file privateKey=id_rsa \
--set-file openrc=.openrc.k8s
