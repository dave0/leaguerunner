#!/usr/bin/perl
use strict;

# serialPHP.pm
# By: Peter H. Li <pli9+CODE@spam-guard.itsa.ucsf.edu>
# Last updated: 4/1/03
# Library for interacting with serialized PHP data.  This will allow you to 
# load PHP data into PERL, manipulate it, and write it back to PHP.
# (best viewed with tabstops every 6 characters)

# Licensed by Creative Commons
# http://creativecommons.org/licenses/by-nc-sa/1.0/

# A nice(r?) OO solution is available on CPAN as PHP::Session::Serializer::PHP.
# At least it looks nice; I've never tried actually using it yet...
# It uses almost identical logic for encoding Perl variables to PHP serials,
# but implements the state machine for decoding the other way and is therefore
# safer.  Only advantages to my version at this point are smaller size, clearer
# logic for decoding since not state machine, and not OO (?).  Disadvantages 
# are no state machine, so less robust in special circumstances (see below), 
# and not OO (?).  Assuming the CPAN version works...

# NB: I didn't bother to take particular care to keep track of datatypes 
# because both PERL and PHP are loosely typed and therefore should 
# theoretically be able to handle it.  For example, if you use this library to
# unserialize and then reserialize a PHP serial string, you may find that your
# booleans and NULLs have been converted into equivalent ints, or even that 
# strings have been converted into numericals.  However, none of this should 
# interfere with your use of these variables in PHP code, where they should be 
# easily recast to their original types, or any other type.

# NB: If you look at the way array unserialization is implemented, you'll see 
# that I chose to use convoluted RegExp parsing to pull out the key/value
# pairs.  This is not quite as robust as making a state machine.
# An example of where my implementation may get you into trouble is if your 
# serialized PHP data includes strings that themselves match the serialized 
# data format, you may run into problems with the RegExps finding the wrong 
# boundaries between array elements.  However, these types of problems will
# only come up in very special cases; in general the RegExp model is highly
# robust and careful to avoid confusions.

# unserialize()
# Will take a serialized PHP data string and convert it into a PERL scalar (for
# NULL, int, float, bool, or string types) or referenced hash (for Array and 
# Object types).  Uses recursive algorithms for arrays and objects.
# Not particularly robust for improperly formed serialized PHP data, so be
# careful what you feed it.
#
# Once you unserialize() an Array or Object, the data entries can be accessed
# by key or by index in PERL.  For example: $arr->{$index} will return the
# item from the array with that numerical index or associative key.  To get
# the number of items in the unserialized array, use the standard PERL:
# scalar(keys(%{$arr}))
sub unserialize {
	my($raw) = $_[0];
	my(@rawList, $type, $len, $body);

	# Split raw data into fields
	@rawList = split(/:/, $raw, 3);

	# Get datatype field
	$type = $rawList[0];

	# Basically a switch operation on datatype possibilities
	if ($type eq 'N;') {
	# ... NULL datatype
		return 0;
	} elsif ($type eq 'i' or $type eq 'd' or $type eq 'b') {
	# ... Integer, Float and Boolean datatypes
		$body = $rawList[1];
		chomp($body);
		return substr($body, 0, -1);
	} elsif ($type eq 's') {
	# ... String datatype
		$len = $rawList[1];
		$body = $rawList[2];
		chomp($body);
		return substr($body, 1, $len);
	} elsif ($type eq 'a') {
	# ... Use a recursive solution for Arrays
		my ($keyMatch, $valMatch, %assoc);
		$keyMatch = 'i:\d+;|s:\d+:".*?";';
		$valMatch = 'N;|b:[01];|i:\d+;|d:\d+.\d+;|s:\d+:".*?";|a:\d+:{.*?}';

		$len = $rawList[1];
		$body = $rawList[2];
		chomp($body);
		$body = substr($body, 1, -1);

		while ($body =~ /^($keyMatch)($valMatch)($keyMatch|$)(.*)/ogs) {
			$assoc{unserialize($1)} = unserialize($2);
			$body = $3 . $4;
		}

		# Return reference to hash; allows multi-layer arrays
		return \%assoc;

	} elsif ($type eq 'O') {
	# ... Use a recursive solution for Objects
		my ($obj, @objList, $className, $objLen, $objBody, $objAssoc);
		my %object;

		$obj = $rawList[2];
		@objList = split(/:/, $obj, 3);

		$className = substr($objList[0], 1, -1);
		$objLen    = $objList[1];
		$objBody   = $objList[2];
		# A little hacky; plunders the Array unserialize logic.
		$objAssoc = unserialize("a:$objLen:$objBody");

		# We must distinguish Objects from Arrays in the internal PERL 
		# representation.  We do this by using an undef hash key
		# 'OBJECT'.  No PHP array should return an undef hash key.
		# Therefore the test exists($hash{'OBJECT'}) combined with
		# not(defined($hash{'OBJECT'})) should work to determine if
		# the hash returned is an array or an object.
		$object{'OBJECT'} = undef;
		$object{'name'}   = $className;
		$object{'len'}    = $objLen;
		$object{'assoc'}  = $objAssoc;

		return \%object;
	}
}

# serialize() 
# Takes a PERL scalar or properly formed (i.e. generated by unserialize() or
# organized accordingly) referenced hash and converts it into a PHP format 
# serialized data string.  Uses recursive algorithms for arrays and objects.
sub serialize {
	my($input) = $_[0];

	if ($input =~ /^\d+$/) {
	# ... Integer datatype
		return "i:$input;";
	} elsif ($input =~ /^\d+\.\d+$/) {
	# ... Float datatype
		return "d:$input;";
	} elsif (ref($input) eq 'HASH') {
	# ... Object and Array datatypes.
		# See serialize() above for explanation of the test used here to 
		# distinguish Object from Array.
		if (exists(${$input}{'OBJECT'}) and 
		    not defined(${$input}{'OBJECT'})) {
		# ... Use a recursive solution for Objects 
			my($className, $objLen, $objAssoc);

			$className = ${$input}{'name'};
			$objLen  = ${$input}{'len'};
			# A little hacky; plunders the Array serialize logic
			$objAssoc = substr(serialize(${$input}{'assoc'}), 4);

			return "O:3:\"$className\":$objLen:$objAssoc";

		} else {
		# ... Use a recursive solution for Arrays 
			my($key, $value, $serKey, $serVal, $hashLen);
			my($serHash) = '';

			while (($key, $value) = each(%{$input})) {
				$serKey = serialize($key);
				$serVal = serialize($value);
				$serHash = $serHash . $serKey . $serVal;
			}

			$hashLen = scalar(keys(%{$input}));

			return "a:$hashLen:{$serHash}";
		}

	} else {
	# ... String datatype
		my $len = length($input);
		return "s:$len:\"$input\";";
	}
}
1;
