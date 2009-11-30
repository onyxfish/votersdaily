#!/usr/bin/python

from fabric.api import *

# GLOBALS

env.project_name = 'votersdaily'

# ENVIRONMENTS
    
def testenv():
    """
    Configure for local test environment.
    """
    env.hosts = ['192.168.1.200']
    env.user = 'sk'
    env.path = '/home/%(user)s/%(project_name)s' % env
    
    env.virtualenvs_path = '/home/%(user)s/.virtualenvs' % env
    env.public_couchdb = True

# COMMANDS

def setup():
    """
    Install Apache and other requirements, create a fresh virtualenv, and make
    required directories.
    """
    require('hosts', provided_by=[testenv])
    require('path', provided_by=[testenv])
    require('virtualenvs_path', provided_by=[testenv])
    require('public_couchdb', provided_by=[testenv])
    
    apt_install('php5 php5-cgi php5-cli')
    apt_install('subversion')
    
    apt_install('python-setuptools')
    easy_install('pip')
    pip_install('virtualenv')
    pip_install('virtualenvwrapper')
    
    apt_install('couchdb')
    
    if env.public_couchdb:
        with cd('/etc/couchdb'):
            sudo('cp local.ini local.ini.bak')
            sudo("sed 's/;bind_address = 127.0.0.1/bind_address = 0.0.0.0/' <local.ini.bak > local.ini")
            sudo('/etc/init.d/couchdb restart')
    
    run('mkdir -p %(path)s' % env)
    run('mkdir -p %(virtualenvs_path)s' % env)
    
    run('source /usr/local/bin/virtualenvwrapper_bashrc && mkvirtualenv --no-site-packages %(project_name)s' % env)

def deploy():
    """
    Deploy the project onto the target environment. Assumes setup() has already
    been run.
    """
    require('hosts', provided_by=[testenv])
    require('path', provided_by=[testenv])
    
    upload_tar_from_git()
    install_requirements()
    
# UTILITIES

def apt_install(package):
    """
    Install a single package on the remote server with Apt.
    """
    sudo('aptitude install -y %s' % package)

def easy_install(package):
    """
    Install a single package on the remote server with easy_install.
    """
    sudo('easy_install %s' % package)

def pip_install(package):
    """
    Install a single package on the remote server with pip.
    """
    sudo('pip install %s' % package)
    
def with_virtualenv(command):
    """
    Executes a command in this project's virtual environment.
    """
    run('source /usr/local/bin/virtualenvwrapper_bashrc && workon %s && %s' % (env.project_name, command))

def upload_tar_from_git():
    """
    Create a tar archive from the current git master branch and upload it.
    """    
    local('git archive --format=tar master | gzip > upload.tar.gz')
    put('upload.tar.gz', '%(path)s' % env)
    
    with cd(env.path):
        run('tar zxf upload.tar.gz', pty=True)
        
    local('rm upload.tar.gz')
    
def install_requirements():
    """
    Install the required packages from the requirements file using pip.
    """    
    with cd(env.path):
        with_virtualenv('pip install -r requirements.txt')