# config valid only for current version of Capistrano
#lock '3.3.5'
set :application, 'lincko'
set :format, :pretty
set :pty, true
set :linked_files, fetch(:linked_files, []).push('composer.lock')
set :linked_dirs, fetch(:linked_dirs, []).push('logs', 'vendor', 'param')
set :keep_releases, 5

set :ssh_options, {
	port: 2244
}

# to avoid an error message at the first launch, create the composer.lock file manually, and copy the current content
# "cd #{shared_path} && nano composer.lock"

namespace :deploy do

	desc 'Init application'
	task :composer do
		on roles(:web), in: :sequence, wait: 1 do
			# On server, do only once manually the follow commands
			# "cd #{shared_path} && chown -R apache:apache logs"
			# "cd #{shared_path} && chown -R apache:apache public"
			# ----------------------------------------------------
			# Update Composer
			execute "cd #{release_path} && composer update"
			# Backup database
			execute "automysqlbackup /etc/automysqlbackup/myserver.conf"
			execute "mkdir -p #{release_path}/mysql/backup && cp -R #{fetch(:root_path)}/deploy/share/db/latest/* #{release_path}/mysql/backup"		
		end
	end

	before :publishing, :composer

	after :restart, :clear_cache do
		on roles(:web), in: :groups, limit: 3, wait: 3 do
			# Do noting
		end
	end

end

