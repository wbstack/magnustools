> ℹ️ Issues for this repository are tracked on [Phabricator](https://phabricator.wikimedia.org/project/board/5563/) - ([Click here to open a new one](https://phabricator.wikimedia.org/maniphest/task/edit/form/1/?tags=wikibase_cloud
))

# magnustools
## About
This repository contains a fork of [magnustools](https://bitbucket.org/magnusmanske/magnustools/src/master/), which is (technically) a PHP library with shared code for wikidata tools over at https://tools.wmflabs.org

This fork contains modifications to use these tools with wbstack / wikibase.cloud

It gets used by:
- [wbstack/widar](https://github.com/wbstack/widar/blob/main/composer.json)
- [wbstack/quickstatements](https://github.com/wbstack/quickstatements/blob/main/composer.json)
- [wbstack/cradle](https://github.com/wbstack/cradle/blob/main/composer.json)

## Syncing this fork
- Switch/Create a branch for the merge
- Add local upstream remote: `git remote add upstream https://bitbucket.org/magnusmanske/magnustools/src/master/`
- Fetch upstream: `git fetch upstream`  
- Merge master(!) branch: `git merge upstream/master`
- Resolve conflicts (if any)
- Update Changelog

## Development in wbstack
In order to test pending changes from this repository with a specific tool, you can target a specific branch from this repository.

- specify branch name in `composer.json` of the tool
  - in this example, `test/my-branch` is the branch name and `dev-` is not part of it ([docs](https://getcomposer.org/doc/articles/versions.md#branches)):
```
    "require": {
      "wbstack/magnustools": "dev-test/my-branch"
    },
```

- run `composer update wbstack/magnustools`

- use skaffold to deploy a local image
  - it may be the case that skaffold does not build a new image automatically, in that case you can build it manually first via `wbaas-deploy/skaffold $ skaffold build --kube-context minikube-wbaas -m tool-$NAME` (where `$NAME` is `cradle` or `quickstatements`, etc)
