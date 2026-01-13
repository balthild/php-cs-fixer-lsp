# PHP CS Fixer Language Server

A Language Server Protocol (LSP) implementation for PHP CS Fixer. Unlike other existing implementations, this one keeps PHP CS Fixer processes running in the background, minimizing the overhead of initialization on each formatting request.

## Installation

If you're using the [vscode extension](https://marketplace.visualstudio.com/items?itemName=balthild.php-cs-fixer-lsp), it will download the language server automatically.

If you need the language server for other editors, you can download it manually from [releases](https://github.com/balthild/php-cs-fixer-lsp/releases), or install it with Phive:

```bash
# I'm too dumb to know how to use GPG, so ¯\_(ツ)_/¯
phive install balthild/php-cs-fixer-lsp --force-accept-unsigned
```

## Building from Source

Compile to a PHAR file:

```
make build
```

The output binary will be located in the `build` directory.

## Fun Fact

This project itself does not use PHP CS Fixer but [Mago](https://github.com/carthage-software/mago). If you don't need the fine-grained control of PHP CS Fixer, give Mago a try because it's fast as ass.
