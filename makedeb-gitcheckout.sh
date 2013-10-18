#!/bin/bash
LABEL=$1
REPO=$2
TAG=$3
PACKTYPE=$4
PACKDIR=/Users/tylers/Sites/build/packages/$LABEL/

#-----[ Directory Moving ]
if [ $PACKTYPE = "setup" ]; then
    #-----[ Setup Git Checkout ]
    $(cd $PACKDIR)
    echo `pwd`
    git init -q $LABEL
    cd $PACKDIR$LABEL
    git remote add -f origin $REPO
    git config core.sparsecheckout true
    echo $LABEL >> $PACKDIR$LABEL.git/info/sparse-checkout
    git pull -q origin $TAG

    #-----[ Organization ]
    echo "./$LABEL/$LABEL/DEBIAN"
    if [ -f ./$LABEL/$LABEL/DEBIAN ]; then
        cp ./$LABEL/$LABEL/DEBIAN/* ./$LABEL/DEBIAN
        chmod -R 755 ./$LABEL/DEBIAN
    fi

    if [ -f $LABEL/$LABEL/deps ]; then
        mv $LABEL/$LABEL/deps ./$LABEL
    fi

    if [ -d $LABEL/$LABEL/overlay ]; then
        mv $LABEL/$LABEL/overlay/* ./$LABEL
    fi
elif [ $PACKTYPE = "site" ]; then
    git clone -q $REPO ./$LABEL/source
    mv ./$LABEL/source/* ./$LABEL/opt/sites/$LABEL
    rm -rf ./$LABEL/source
fi

#-----[ Cleanup ]
#rm -Rf .git
#rm -Rf $LABEL