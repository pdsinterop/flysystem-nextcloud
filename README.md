# Flysystem Nextcloud

[![PDS Interop][pdsinterop-shield]][pdsinterop-site]
[![Project stage: Development][project-stage-badge: Development]][project-stage-page]
[![License][license-shield]][license-link]
[![Latest Version][version-shield]][version-link]
[![standard-readme compliant][standard-readme-shield]][standard-readme-link]
![Maintained][maintained-shield]

_Flysystem adapter for the Nextcloud filesystem_

## Table of Contents

<!-- toc -->

- [Background](#background)
- [Installation](#installation)
- [Usage](#usage)
    - [Adapter](#adapter)
    - [Plugin](#plugin)
- [Contribute](#contribute)
- [License](#license)

<!-- tocstop -->

## Background

This project is part of the PHP stack of projects by PDS Interop. It is used by
the Solid-Nextcloud app.

As the functionality seemed useful for other projects, it was implemented as a
separate package.

## Installation

The advised install method is through composer:

```
composer require pdsinterop/flysystem-nextcloud
```

## Usage

This package offers features to interact with the Filesystem provided by
Nextcloud through the Flysystem API.

To use the adapter, instantiate it and add it to a Flysystem filesystem:

```php
<?php
/** @var IRootFolder $rootFolder */
$folder = $rootFolder->getUserFolder('userId')->get('/some/directory');

// Create the Nextcloud Adapter
$adapter = new \Pdsinterop\Flysystem\Adapter\Nextcloud($folder);

// Create Flysystem as usual, adding the Adapter
$filesystem = new \League\Flysystem\Filesystem($adapter);

// Read the contents of a file
$content = $filesystem->read('/some.file');
```

## Contribute

Questions or feedback can be given by [opening an issue on GitHub](https://github.com/pdsinterop/flysystem-nextcloud/issues).

All PDS Interop projects are open source and community-friendly. 
Any contribution is welcome!
For more details read the [contribution guidelines](contributing.md).

All PDS Interop projects adhere to [the Code Manifesto](http://codemanifesto.com)
as its [code-of-conduct](CODE_OF_CONDUCT.md). Contributors are expected to abide by its terms.

There is [a list of all contributors on GitHub][contributors-page].

For a list of changes see the [CHANGELOG](CHANGELOG.md) or the GitHub releases page.

## License

All code created by PDS Interop is licensed under the [MIT License][license-link].

[contributors-page]:  https://github.com/pdsinterop/flysystem-nextcloud/contributors
[license-link]: ./LICENSE
[license-shield]: https://img.shields.io/github/license/pdsinterop/flysystem-nextcloud.svg
[maintained-shield]: https://img.shields.io/maintenance/yes/2020
[pdsinterop-shield]: https://img.shields.io/badge/-PDS%20Interop-gray.svg?logo=data%3Aimage%2Fsvg%2Bxml%3Bbase64%2CPHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9Ii01IC01IDExMCAxMTAiIGZpbGw9IiNGRkYiIHN0cm9rZS13aWR0aD0iMCI+CiAgICA8cGF0aCBkPSJNLTEgNTJoMTdhMzcuNSAzNC41IDAgMDAyNS41IDMxLjE1di0xMy43NWEyMC43NSAyMSAwIDAxOC41LTQwLjI1IDIwLjc1IDIxIDAgMDE4LjUgNDAuMjV2MTMuNzVhMzcgMzQuNSAwIDAwMjUuNS0zMS4xNWgxN2EyMiAyMS4xNSAwIDAxLTEwMiAweiIvPgogICAgPHBhdGggZD0iTSAxMDEgNDhhMi43NyAyLjY3IDAgMDAtMTAyIDBoIDE3YTIuOTcgMi44IDAgMDE2OCAweiIvPgo8L3N2Zz4K
[pdsinterop-site]: https://pdsinterop.org/
[project-stage-badge: Development]: https://img.shields.io/badge/Project%20Stage-Development-yellowgreen.svg
[project-stage-page]: https://blog.pother.ca/project-stages/
[standard-readme-link]: https://github.com/RichardLitt/standard-readme
[standard-readme-shield]: https://img.shields.io/badge/readme%20style-standard-brightgreen.svg
[version-link]: https://packagist.org/packages/pdsinterop/flysystem-nextcloud
[version-shield]: https://img.shields.io/github/v/release/pdsinterop/flysystem-nextcloud?sort=semver
