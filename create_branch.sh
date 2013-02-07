#!/bin/bash

set -ex

BRANCH_LIST=branch_list
JOB_DIR=/var/lib/jenkins/jobs

generate_git_branch_list() {
    for branch in $(git for-each-ref --format='%(refname)' refs/heads/); do
        last_sha1_in_repo=$(git log --oneline -1 $branch | cut -d' ' -f1)
        echo "$last_sha1_in_repo $branch"
    done
}

list_git_heads() {
    changed_branches=""
    for branch in $(git for-each-ref --format='%(refname)' refs/heads/); do
        last_sha1_in_repo=$(git log --oneline -1 $branch | cut -d' ' -f1)
        last_sha1_saved=$(grep -e "^[[:alnum:]]* $branch$" $BRANCH_LIST | cut -d' ' -f1)
        if [ "$last_sha1_in_repo" != "$last_sha1_saved" ]; then
            changed_branches="$changed_branches $branch"
        fi
    done
    echo $changed_branches
}

job_exists() {
    if [ -d "$JOB_DIR/$1" ]; then
	return 0
    fi
    return 1
}

jenkins-cli() {
    #echo "jenkins-cli $@"
    java -jar /var/lib/jenkins/war/WEB-INF/jenkins-cli.jar -s http://localhost:8080/jenkins $@
}

create_job() {
    config_file=$1
    branch_name=$2
    job_name=$3
    if ! job_exists $job_name; then
	cat "$config_file" | sed -e "s/@@BRANCH_NAME@@/$branch_name/" | jenkins-cli create-job "$job_name"
	echo "New job: $job_name"
	jenkins-cli enable-job "$job_name"
    fi
}

create_job_php53() {
    branch_name=$1
    create_job "$JOB_DIR/ut_template_php53/config.xml" "$branch_name" "ut_php53_$branch_name"
	
}

create_job_php51() {
    branch_name=$1
    create_job "$JOB_DIR/ut_template_git/config.xml" "$branch_name" "ut_$branch_name"
}

create_job_js() {
    branch_name=$1
    create_job "$JOB_DIR/ut_template_js/config.xml" "$branch_name" "ut_js_$branch_name"
}

# If no branch list, first init, do not create any jobs
if [ ! -f "$BRANCH_LIST" ]; then
    generate_git_branch_list  > $BRANCH_LIST
    exit
fi

if [ ! -d "$JOB_DIR" ]; then
    echo "JOB_DIR not set"
fi

for head in $(list_git_heads); do
    branch_name=$(echo "$head" | sed -e 's%refs/heads/%%')
    create_job_php53 $branch_name
    create_job_php51 $branch_name
    create_job_js $branch_name
done
generate_git_branch_list  > $BRANCH_LIST
