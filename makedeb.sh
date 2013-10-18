#!/bin/bash

export GPGKEY=C69CA220

function die
{
  echo -e "$*";
  exit 1;
}

if [ "$#" -eq 0 ]; then
    die "Usage:\n\t$0 package_name package_name ...\n"
fi

#PATH_WORKING=`echo $0 | sed s/"\/$(basename $0)"//`
PATH_WORKING=/Users/tylers/Sites/DG-setup
PATH_PACKAGES=$PATH_WORKING/packages
PATH_BINARIES=$PATH_WORKING/binaries
PATH_REPO=/debian/DG
CONFIG=$PATH_WORKING/makedeb-remotes.ini

PKG_PREFIX='dgrp-'
PKG_VER=1

SVN_ROOT="http://svn.nationalfibre.net/repo"
SVNBIN="svn"
CREDENTIALS="--username tylers"
TDIR=/tmp/deb.$RANDOM
REPO=/debian/instaclick
REBUILD=/tmp/rebuild.$$

for PKG in $*
{
    echo '***' preparing data for packaging role $PKG

    if ! mkdir -p "$PATH_PACKAGES"; then
        echo unable to create "$PATH_PACKAGES"
        exit 70
    fi

    PKG_CONFIG=`grep ^$PKG $CONFIG`
    PKG_PARAMS=`echo $PKG_CONFIG | cut -d"|" -f2`
    PKG_type=`echo $PKG_PARAMS | cut -d"," -f1 | sed s/type=//`
    PKG_url=`echo $PKG_PARAMS | cut -d"," -f2 | sed s/url=//`
    PKG_label=`echo $PKG_PARAMS | cut -d"," -f3 | sed s/label=//`
    PKG_tag=`echo $PKG_PARAMS | cut -d"," -f4 | sed s/tag=//`
    PKG_description=`echo $PKG_PARAMS | cut -d"," -f5 | sed s/description=// | sed s/\"//g`
    PKG_role=`echo $PKG_PARAMS | cut -d"," -f6 | sed s/role=//`
    PKG_DIR=$PATH_PACKAGES/$PKG
    if [ "$PKG_type" = "site" ]; then
        PKG_DOWNLOAD_DIR="$PKG_DIR/opt/sites/$PKG_label"
    else
        PKG_DOWNLOAD_DIR=$PKG_DIR
    fi

    # Setting up the package's repository and fetching the contents
    # from the repository url into the download directory
    git init -q $PKG_DOWNLOAD_DIR
    cd $PKG_DOWNLOAD_DIR
    git remote add -f origin $PKG_url
    # IF the package is a setup package, set the .git checkout to be
    # a sparse one and define the package's directory as the only
    # directory to be downloaded
    if [ "$PKG_type" = "setup" ]; then
        git config core.sparsecheckout true
        echo $PKG_label > .git/info/sparse-checkout
    fi
    git pull -q origin $PKG_tag
    # Getting the short revisioh hash from the repository to use in
    # package control file
    #PKG_rev=`git rev-parse --short HEAD`
    PKG_rev=`date +"%y%m%d%H%M"`
    # Cleaning up the repository directory that is not needed/wanted
    # when building the package
    rm -Rf .git

    # Moving back into the package directory for DEBIAN related changes
    # and building
    cd $PKG_DIR

    # Creating the DEBIAN directory to house the pre/post scripts,
    # as well as the control file
    mkdir -p DEBIAN

    # If the package has a DEBIAN directory, move it from the repo
    # to the build directory
    if [ -d $PKG_label/DEBIAN ]; then
        mv $PKG_label/DEBIAN/* DEBIAN
        chmod -R 755 DEBIAN
    fi

    # checking dependancy
    if [ -f "$PKG_label/deps" ]; then
        DEPS=`head -1 $PKG_label/deps`
    else
        DEPS='bash'
    fi
    if [ "$PKG_type" = "site" ]; then
        DEPS="$DEPS, $PKG_role"
    fi

    # Creating the control file
    PLIST="DEBIAN/control"

    cat <<EOF > $PLIST
Package: ${PKG_PREFIX}${PKG_type}-${PKG}
Section: daxiangroup
Version: $PKG_VER.131018-$PKG_rev
Priority: required
Maintainer: Tyler Schwartz
Architecture: all
Depends: ${DEPS}
Description: $PKG_description
EOF

    # Copying anything in the overlay directory in case there are
    # things we want/need to install in specific directories
    if [ -d $PKG_label/overlay ]; then
        if ! cp -r $PKG_label/overlay/* .; then
            echo Unable to copy $PKG_label/overlay to $PKG_DIR
            exit 1
        fi
    fi

    # Cleaning up the directory checked out from the repository
    rm -Rf $PKG_label

    #get current revision
    #REV=`$SVNBIN info $CREDENTIALS -rHEAD $SVN_ROOT/dev/branches/$TAG|grep '^Last Changed Rev:'|cut -b19-`

    #touch $REBUILD
    #PREV=0

    # exit with error code 70 if we cannot obtain code module from SVN
    #if ! $SVNBIN export $CREDENTIALS -q -rHEAD $SVN_ROOT/dev/branches/$TAG/setup/linux/$PKG $PKGDIR; then
    #    echo unable to fetch module $PKG from $SVN_ROOT/dev/branches/$TAG/setup/linux/$PKG
    #    exit 70
    #fi


#####{ Not sure if we need this }#####
#    #get code
#    if [ -d "$PKGDIR/code" ]; then
#        for module in `ls -1 "$PKGDIR/code"`
#        {
#            cat $PKGDIR/code/$module|while read module_line
#            do
#            set $module_line
#            case $1 in
#            svn)
#            SVN_PATH=`echo $3 | sed s/'$TAG'/$TAG/g`
#
#            touch $REBUILD
#            echo getting $SVN_PATH rev $2 via SVN
#            if ! $SVNBIN export $CREDENTIALS -q -r$2 $SVN_ROOT/$SVN_PATH $TDIR/debian$4; then
#                echo Unable to fetch $module from $SVN_PATH
#                exit 70
#            fi
#            echo ${REV} ${SVN_ROOT}/$SVN_PATH > $TDIR/debian$4/.revinfo
#
#            ;;
#            *)
#            echo "unknown method: $1 in module: $module"
#            exit 70
#            ;;
#            esac
#
#            #code to move out
#            #echo "rm -rf $4" >> $TDIR/debian/DEBIAN/postrm
#
#            done
#            if [ $? != 0 ]; then
#            echo cannot extract all code modules
#            exit 1
#            fi
#        }
#
##    fi
#####{ Not sure if we need this }#####


#    #copy package overlay
#    if [ -d $PKGDIR/overlay ]; then
#    if ! cp -r $PKGDIR/overlay/* $TDIR/debian/; then
#        echo Unable to copy $PKGDIR/overlay to $TDIR/debian
#        exit 1
#    fi
#    fi

#Run package hook if found...
#if [ -f "$TDIR/$PKG/packagehook" ]; then
#   source "$TDIR/$PKG/packagehook";
#fi
#    PLIST="$TDIR/debian/DEBIAN/control"
#
#    cat <<EOF > $PLIST
#Package: ${PKG_PREFIX}${PKG}
#Section: sexsearch
#Version: 1.${REV}-$TAG
#Priority: required
#Maintainer: Steve Pereira
#Architecture: all
#Depends: ${DEPS}
#Description: debian package for $PKG
#EOF
#if [ -f "$TDIR/$PKG/extra.meta" ]; then
#   cat "$TDIR/$PKG/extra.meta" >>$PLIST
#fi

    #if [ -f $REBUILD ]; then
    #       echo ' *** ' Building new package ${REPO}/binary/${PKG_PREFIX}${PKG}_${PKG_VER}.${REV}_all.deb
    #   mv ${REPO}/binary/${PKG_PREFIX}${PKG}_*_all.deb ${REPO}/binary-archive/
    cd $PATH_WORKING
    dpkg -b $PKG_DIR $PATH_BINARIES/${PKG_PREFIX}${PKG_type}-${PKG}_${PKG_VER}.${PKG_rev}_all.deb
    #rm -f $REBUILD
    #else
    #    echo No difference in package ${PKG} between rev ${PREV} and ${REV}
    #fi
    rm -rf "$PKG_DIR"
}

#echo Rebuilding instaclick repository
#cd $REPO
#dpkg-scanpackages binary /dev/null | bzip2 -9c > binary/Packages.bz2
#dpkg-scansources source /dev/null | bzip2 -9c > source/Sources.bz2

#svn cleanup
#rm -rf ~/.subversion

exit 0