# Install

These instructions allow you to install all the required Python 3 and Ansible Galaxy dependencies locally (in a Python 3 virtualenv for ansible itself and other Python 3 dependencies, and in a subfolder for the Ansible Galaxy roles).

You need to install Python 3 and pip3 globally on your system.

```
pip3 install virtualenv
```

Create a python3 virtual env in `./env` :

```
virtualenv --python=python3 env
```

Change your current shell environnement variables so that running python or pip will actually use your local installation of Python 3.

```
source env/bin/activate
```

Install the Python 3 dependencies from PyPi:

```
pip install -r requirements.txt
```

Install the openstack openrc file

```
vim .openrc
```

Install Mitogen to boost ansible playbooks

```
wget https://networkgenomics.com/try/mitogen-0.2.9.tar.gz
tar -xzf mitogen-0.2.9.tar.gz
rm mitogen-0.2.9.tar.gz
```

# Use

Load the virtual env to get the latest ansible CLI commands

```
source env/bin/activate
source .openrc
```
