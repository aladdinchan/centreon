#!/bin/bash

# Simple po file translate script through google translate api v2.
# Tested on macOS 10.15
# Author : CHEN JUN<aladdin.china@gmai.com> , 2019

POFILE_IN=$1
POFILE_OUT=$2
APIKEY="XXXXXXXXXXnYXRshXvPytqEPS1zXPZbXbNX2C9M" # Replaced by your own google translate api key.
SOURCELANG="en"
TARGETLANG="zh-Hans"

MSGID_START=False
while read -r LINE; do
    if [[ "${LINE:0:5}" == "msgid" ]] ; then
        # msgid may have multiple line.
        MSGID_START=True
        MSGID=${LINE:7:${#LINE}-8} # Message in double quotes.
    elif [[ "${LINE:0:6}" == "msgstr" ]] ; then 
        # One msgid readed, tranlate through google api.
        if [[ "$MSGID" != "" ]] ; then
            MSGSTR=`curl -s -X POST \
                "https://translation.googleapis.com/language/translate/v2?key=$APIKEY" \
                -H 'content-type: application/json' \
                -d "{
                \"q\": \"$MSGID\",
                \"source\": \"$SOURCELANG\",
                \"target\": \"$TARGETLANG\",
                \"format\": \"text\"
                }"`
            if [[ "$?" != "0" ]] ; then
                echo "Error on call google translation api v2."
                echo "$MSGSTR"
                exit 1
            fi
            #MSGSTR1=`echo $MSGSTR | grep translatedText | cut -d \" -f 8` # When there are double quotes in the result, only partial result is returned.
            MSGSTR1=`echo $MSGSTR | sed 's/.*translatedText":\ "\(.*\)"\ .*$/\1/g'`
            if [[ "$MSGSTR1" == "" ]] ; then
                echo "Something wrong."
                echo "$MSGSTR"
                exit 1
            fi
            MSGSTR="msgstr \"$MSGSTR1\""
        else 
            MSGSTR="msgstr \"\""   # Blank msgid.
        fi
        echo "$MSGSTR" >> $POFILE_OUT
        
        # Next msgid
        MSGID=""
        MSGID_START=False
        continue
    elif [[ "$MSGID_START" == "True" ]] ; then
        MSGID1=${LINE:1:${#LINE}-2}
        if [[ "$MSGID" == "" ]] ; then
            MSGID="$MSGID1"
        else
            MSGID="$MSGID $MSGID1"
        fi
    fi
    echo "$LINE" >> $POFILE_OUT   # Double quotes is necessary, because msgid may having leading spaces.
done < $POFILE_IN

#End