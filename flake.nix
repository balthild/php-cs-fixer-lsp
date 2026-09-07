{
  inputs = {
    flake-parts.url = "github:hercules-ci/flake-parts";
    nixpkgs.url = "github:cachix/devenv-nixpkgs/rolling";
    devenv.url = "github:cachix/devenv";
  };

  nixConfig = {
    extra-trusted-public-keys = "devenv.cachix.org-1:w1cLUi8dv3hnoSPGAuibQv+f9TZLr6cv/Hm9XgU50cw=";
    extra-substituters = "https://devenv.cachix.org";
  };

  outputs = inputs@{ flake-parts, nixpkgs, ... }:
    flake-parts.lib.mkFlake { inherit inputs; } {
      imports = [ inputs.devenv.flakeModule ];
      systems = nixpkgs.lib.systems.flakeExposed;

      perSystem = { pkgs, config, ... }: {
        packages = {
          php82-zts = pkgs.php82.override { ztsSupport = true; };
        };

        devenv.shells.default = {
          name = "php-cs-fixer-lsp";

          packages = [ pkgs.php82Packages.box ];

          languages.php = {
            enable = true;
            package = config.packages.php82-zts.buildEnv {
              extensions = { all, enabled }: with all; enabled ++ [
                xdebug
                opcache
                parallel
              ];
              extraConfig = ''
                xdebug.mode = debug
              '';
            };
          };
        };
      };
    };
}
