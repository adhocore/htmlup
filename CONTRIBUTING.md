## How to contribute

### Before you start

<!-- This section contains conventions/standards contributors must follow -->
<!-- For example: Commit messages should follow angular standard -->

### Setting up

You may need to fork this project in [GitHub](https://github.com/adhocore/htmlup).

```sh
git clone git@github.com:adhocore/htmlup.git

# OR if you have a fork
git clone git@github.com:<your_github_handle>/htmlup.git

# You may also add upstream
git remote add upstream https://github.com/adhocore/htmlup.git

cd htmlup

# Create a new branch
git checkout -b $branch_name

# Install deps
composer install -o
```

### Moving forward

```sh
# Open htmlup in IDE
subl htmlup

# ... and do the needful

# Optionally run the lint
for P in src tests; do find $P -type f -name '*.php' -exec php -l {} \;; done

# ... and phpcs fixer or stuffs like that!

# Run tests
vendor/bin/phpunit --coverage-text


# If your feature takes long your dev branch might be out of sync, you may want to
git checkout $branch_name
git pull upstream master # branch could be something else than master
```

### Finalizing

Everything looking good?

```sh
# Commit your stuffs
git add $file ...$files
git commit -m "..."

# Push 'em
git push origin HEAD
```

Now goto [GitHub](https://github.com/adhocore/htmlup/compare?expand=1), select your branch and create PR.

### Getting PR merged

You have to wait. You have to address change requests. Be patient.

Thank you for contribution!

**Lastly** Please be informed that your works will be licensed same as the project [license](./LICENSE)
