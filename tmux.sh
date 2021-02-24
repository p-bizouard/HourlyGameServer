#!/usr/bin/env bash

tmux \
  new-session "docker-compose up" \; \
  splitw -fh  "zsh" \; \
  resize-pane -L 30 \; 
 
