cd tuleap.git
git fetch --all
/var/lib/jenkins/create_branch.sh
curl http://localhost:8080/jenkins/git/notifyCommit?url=$WORKSPACE/tuleap.git
