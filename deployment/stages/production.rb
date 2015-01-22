set :root_path, "/glusterfs/.lincko.com"
set :repo_url, "ssh://deploy@lincko.com:2244#{fetch(:root_path)}/deploy/share/git/slim.web"
set :deploy_to, "#{fetch(:root_path)}/www/share/slim.web"
role :web, %w{deploy@lincko.com}
#server 'lincko.com', user: 'deploy', roles: %w{web}, port: 2244, my_property: :my_value

