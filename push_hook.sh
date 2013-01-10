#!/bin/bash -xe

# Synchronize local mirror
cd $WORKSPACE/tuleap.git
git fetch --all

# Create branches
$WORKSPACE/tuleap2jenkins/create_branch.sh

# Notify jobs that depends of this repo
curl -k $JENKINS_URL/git/notifyCommit?url=$WORKSPACE/tuleap.git
